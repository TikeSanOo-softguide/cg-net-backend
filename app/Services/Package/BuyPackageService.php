<?php

namespace App\Services\Package;

use App\Enums\CustomerPackageStatus;
use App\Enums\LedgerAccountCode;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\PackageOrderStatus;
use App\Enums\WalletActorType;
use App\Enums\WalletStatus;
use App\Models\CustomerPackage;
use App\Models\LedgerTransaction;
use App\Models\Package;
use App\Models\PackageOrder;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Ledger\LedgerPoster;
use App\Services\PackageActivation\PackageActivationResult;
use App\Services\PackageActivation\PackageActivationServiceInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class BuyPackageService
{
    public function __construct(
        protected PackageActivationServiceInterface $activationService,
        protected LedgerPoster $ledger,
    ) {}

    /**
     * Debit and the package order commit together.
     * Activation runs only after that commit, outside any database transaction.
     * A rejected or thrown activation refunds the debit.
     * A failure while saving the customer package does not refund: activation already succeeded.
     *
     * @return array{http_status: int, body: array<string, mixed>}
     */
    public function buy(
        User $user,
        int $packageId,
        string $idempotencyKey,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        $wallet = $user->wallet;

        if (! $wallet) {
            return $this->response(404, [
                'success' => false,
                'message' => 'Wallet not found.',
            ]);
        }

        if ($wallet->status !== WalletStatus::Active) {
            return $this->response(403, [
                'success' => false,
                'message' => 'Wallet is not active.',
            ]);
        }

        $wallet->refresh();

        $existing = LedgerTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $this->existingResponse($existing, $packageId);
        }

        $package = Package::query()->with('term')->whereKey($packageId)->first();

        if (! $package || ! $package->is_active) {
            throw ValidationException::withMessages([
                'package_id' => ['The selected package is invalid or unavailable.'],
            ]);
        }

        $amount = (int) $package->price;
        $termMonths = (int) ($package->term?->months ?? 0);

        if ($amount < 1 || $termMonths < 1) {
            throw ValidationException::withMessages([
                'package_id' => ['The selected package is invalid or unavailable.'],
            ]);
        }

        if ($wallet->balance < $amount) {
            return $this->insufficientBalanceResponse($package, $amount);
        }

        try {
            $purchased = $this->debitWalletForPackage(
                wallet: $wallet,
                user: $user,
                package: $package,
                amount: $amount,
                termMonths: $termMonths,
                idempotencyKey: $idempotencyKey,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );
        } catch (UniqueConstraintViolationException) {
            return $this->replayFromKey($wallet, $idempotencyKey, $packageId);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        if ($purchased === null) {
            return $this->replayFromKey($wallet, $idempotencyKey, $packageId);
        }

        /** @var PackageOrder $order */
        $order = $purchased['order'];
        /** @var LedgerTransaction $debitTransaction */
        $debitTransaction = $purchased['transaction'];

        try {
            $result = $this->activationService->activate($user, $package, $order);
        } catch (Throwable $exception) {
            report(new RuntimeException('Package activation threw an exception.', previous: $exception));
            $this->refundDebit($order, $wallet, $user, $debitTransaction);

            return $this->failureResponse($package->id, $amount, $order, $debitTransaction);
        }

        if (! $this->activationSucceeded($result)) {
            $this->refundDebit($order, $wallet, $user, $debitTransaction);

            return $this->failureResponse($package->id, $amount, $order, $debitTransaction);
        }

        try {
            $customerPackage = $this->completePurchase($order, $debitTransaction, $user, $package, $termMonths, $result);
        } catch (Throwable $exception) {
            report(new RuntimeException('Package purchase could not be saved after activation.', previous: $exception));

            return $this->response(500, [
                'success' => false,
                'message' => 'Package purchase could not be saved. The wallet debit is still processing.',
                'data' => [
                    'package_id' => $package->id,
                    'amount' => $amount,
                    'status' => PackageOrderStatus::Processing->value,
                    'order_id' => $order->id,
                    'transaction_no' => $debitTransaction->transaction_no,
                ],
            ]);
        }

        $order->refresh();
        $debitTransaction->refresh();

        return $this->response(200, [
            'success' => true,
            'message' => 'Package purchased successfully.',
            'data' => $this->successData($package->id, $amount, $order, $debitTransaction, $customerPackage),
        ]);
    }

    /**
     * @return array{order: PackageOrder, transaction: LedgerTransaction}|null
     */
    protected function debitWalletForPackage(
        Wallet $wallet,
        User $user,
        Package $package,
        int $amount,
        int $termMonths,
        string $idempotencyKey,
        ?string $ipAddress,
        ?string $userAgent,
    ): ?array {
        return DB::transaction(function () use (
            $wallet,
            $user,
            $package,
            $amount,
            $termMonths,
            $idempotencyKey,
            $ipAddress,
            $userAgent,
        ) {
            $transaction = $this->ledger->debitWallet(
                wallet: $wallet,
                amount: $amount,
                contraAccount: LedgerAccountCode::PackageRevenue,
                type: LedgerTransactionType::WifiPackage,
                status: LedgerTransactionStatus::Processing,
                idempotencyKey: $idempotencyKey,
                actorType: WalletActorType::User,
                actorId: $user->id,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );

            $ownsThisPurchase = (int) $transaction->wallet_id === (int) $wallet->id
                && $transaction->type === LedgerTransactionType::WifiPackage
                && $transaction->idempotency_key === $idempotencyKey
                && $transaction->status === LedgerTransactionStatus::Processing;

            $alreadyLinked = PackageOrder::query()
                ->where('ledger_transaction_id', $transaction->id)
                ->exists();

            if (! $ownsThisPurchase || $alreadyLinked) {
                return null;
            }

            $order = PackageOrder::query()->create([
                'user_id' => $user->id,
                'package_id' => $package->id,
                'ledger_transaction_id' => $transaction->id,
                'status' => PackageOrderStatus::Processing,
                'snapshot' => $this->snapshot($package, $amount, $termMonths),
            ]);

            return [
                'order' => $order,
                'transaction' => $transaction,
            ];
        });
    }

    protected function completePurchase(
        PackageOrder $order,
        LedgerTransaction $debitTransaction,
        User $user,
        Package $package,
        int $termMonths,
        PackageActivationResult $result,
    ): CustomerPackage {
        if ($termMonths < 1 || blank($result->username) || blank($result->password)) {
            throw new RuntimeException('Package purchase could not be completed.');
        }

        return DB::transaction(function () use ($order, $debitTransaction, $user, $package, $termMonths, $result) {
            /** @var PackageOrder $lockedOrder */
            $lockedOrder = PackageOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            /** @var LedgerTransaction $lockedDebit */
            $lockedDebit = LedgerTransaction::query()->whereKey($debitTransaction->id)->lockForUpdate()->firstOrFail();

            $existingPackage = CustomerPackage::query()
                ->where('package_order_id', $lockedOrder->id)
                ->first();

            if ($existingPackage) {
                return $existingPackage;
            }

            if (
                $lockedOrder->status !== PackageOrderStatus::Processing
                || $lockedDebit->status !== LedgerTransactionStatus::Processing
            ) {
                throw new RuntimeException('Package purchase could not be completed.');
            }

            $startsAt = now();
            $expiresAt = $startsAt->copy()->addMonths($termMonths);

            $customerPackage = CustomerPackage::query()->create([
                'user_id' => $user->id,
                'package_id' => $package->id,
                'package_order_id' => $lockedOrder->id,
                'username' => $result->username,
                'password' => $result->password,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'status' => CustomerPackageStatus::Active,
            ]);

            $lockedOrder->update([
                'status' => PackageOrderStatus::Completed,
                'completed_at' => $startsAt,
                'ledger_transaction_id' => $lockedDebit->id,
            ]);

            $lockedDebit->update(['status' => LedgerTransactionStatus::Completed]);

            return $customerPackage;
        });
    }

    protected function refundDebit(
        PackageOrder $order,
        Wallet $wallet,
        User $user,
        LedgerTransaction $debitTransaction,
    ): LedgerTransaction {
        return DB::transaction(function () use ($order, $wallet, $user, $debitTransaction) {
            /** @var LedgerTransaction $lockedDebit */
            $lockedDebit = LedgerTransaction::query()->whereKey($debitTransaction->id)->lockForUpdate()->firstOrFail();
            /** @var PackageOrder $lockedOrder */
            $lockedOrder = PackageOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->customerPackage()->exists()) {
                throw new RuntimeException('Cannot refund a package purchase that already has a customer package.');
            }

            $existingRefund = LedgerTransaction::query()
                ->where('reversal_of', $lockedDebit->id)
                ->where('type', LedgerTransactionType::Refund)
                ->lockForUpdate()
                ->first();

            if ($existingRefund) {
                $this->markOrderFailed($lockedOrder);

                return $existingRefund;
            }

            if ($lockedDebit->status === LedgerTransactionStatus::Completed) {
                throw new RuntimeException('Cannot refund a completed package purchase.');
            }

            if ($lockedDebit->status === LedgerTransactionStatus::Failed) {
                $existingRefund = LedgerTransaction::query()
                    ->where('reversal_of', $lockedDebit->id)
                    ->where('type', LedgerTransactionType::Refund)
                    ->firstOrFail();

                $this->markOrderFailed($lockedOrder);

                return $existingRefund;
            }

            try {
                $refundTransaction = $this->ledger->creditWallet(
                    wallet: $wallet,
                    amount: (int) $lockedDebit->amount,
                    contraAccount: LedgerAccountCode::PackageRevenue,
                    type: LedgerTransactionType::Refund,
                    status: LedgerTransactionStatus::Completed,
                    idempotencyKey: 'refund:' . $lockedDebit->idempotency_key,
                    actorType: WalletActorType::System,
                    actorId: $user->id,
                    reversalOf: $lockedDebit->id,
                );
            } catch (UniqueConstraintViolationException) {
                $refundTransaction = LedgerTransaction::query()
                    ->where('idempotency_key', 'refund:' . $lockedDebit->idempotency_key)
                    ->firstOrFail();
            }

            $lockedDebit->update(['status' => LedgerTransactionStatus::Failed]);
            $this->markOrderFailed($lockedOrder);

            return $refundTransaction->refresh();
        });
    }

    protected function markOrderFailed(PackageOrder $order): void
    {
        if ($order->status === PackageOrderStatus::Failed) {
            return;
        }

        $order->update([
            'status' => PackageOrderStatus::Failed,
        ]);
    }

    protected function activationSucceeded(PackageActivationResult $result): bool
    {
        return $result->success && filled($result->username) && filled($result->password);
    }

    /**
     * @return array{http_status: int, body: array<string, mixed>}
     */
    protected function replayFromKey(Wallet $wallet, string $idempotencyKey, int $packageId): array
    {
        $existing = LedgerTransaction::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if (! $existing || (int) $existing->wallet_id !== (int) $wallet->id) {
            return $this->conflict('This idempotency key was already used.');
        }

        return $this->existingResponse($existing, $packageId);
    }

    /**
     * @return array{http_status: int, body: array<string, mixed>}
     */
    protected function existingResponse(LedgerTransaction $existing, int $packageId): array
    {
        if ($existing->type !== LedgerTransactionType::WifiPackage) {
            return $this->conflict('This idempotency key was already used.');
        }

        $order = PackageOrder::query()
            ->where('ledger_transaction_id', $existing->id)
            ->first();

        if (! $order) {
            return $this->conflict('This idempotency key was already used.');
        }

        if ((int) $order->package_id !== $packageId) {
            return $this->conflict('This idempotency key was already used for a different package.');
        }

        if (
            $existing->status === LedgerTransactionStatus::Completed
            && $order->status === PackageOrderStatus::Completed
        ) {
            $customerPackage = $order->customerPackage;

            if (! $customerPackage) {
                return $this->conflict('This package purchase cannot be replayed.');
            }

            return $this->response(200, [
                'success' => true,
                'message' => 'Package purchase already processed.',
                'data' => $this->successData($packageId, (int) $existing->amount, $order, $existing, $customerPackage),
            ]);
        }

        if (
            $existing->status === LedgerTransactionStatus::Failed
            || $order->status === PackageOrderStatus::Failed
        ) {
            $refund = LedgerTransaction::query()
                ->where('reversal_of', $existing->id)
                ->where('type', LedgerTransactionType::Refund)
                ->first();

            return $this->response(502, [
                'success' => false,
                'message' => 'Package activation failed. Wallet was refunded.',
                'data' => [
                    'package_id' => $packageId,
                    'amount' => (int) $existing->amount,
                    'status' => PackageOrderStatus::Failed->value,
                    'order_id' => $order->id,
                    'transaction_no' => $existing->transaction_no,
                    'refund_transaction_no' => $refund?->transaction_no,
                ],
            ]);
        }

        if ($existing->status === LedgerTransactionStatus::Processing && $order->status === PackageOrderStatus::Processing) {
            return $this->response(202, [
                'success' => true,
                'message' => 'This package purchase is already being processed.',
                'data' => [
                    'package_id' => $packageId,
                    'amount' => (int) $existing->amount,
                    'status' => PackageOrderStatus::Processing->value,
                    'order_id' => $order->id,
                    'transaction_no' => $existing->transaction_no,
                ],
            ]);
        }

        return $this->conflict('This package purchase cannot be replayed.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function successData(
        int $packageId,
        int $amount,
        PackageOrder $order,
        LedgerTransaction $transaction,
        CustomerPackage $customerPackage,
    ): array {
        return [
            'package_id' => $packageId,
            'amount' => $amount,
            'status' => PackageOrderStatus::Completed->value,
            'order_id' => $order->id,
            'transaction_no' => $transaction->transaction_no,
            'customer_package_id' => $customerPackage->id,
            'expires_at' => $customerPackage->expires_at?->toISOString(),
        ];
    }

    /**
     * @return array{http_status: int, body: array<string, mixed>}
     */
    protected function failureResponse(
        int $packageId,
        int $amount,
        PackageOrder $order,
        LedgerTransaction $debitTransaction,
    ): array {
        $order->refresh();

        $refund = LedgerTransaction::query()
            ->where('reversal_of', $debitTransaction->id)
            ->where('type', LedgerTransactionType::Refund)
            ->first();

        return $this->response(502, [
            'success' => false,
            'message' => 'Package activation failed. Wallet was refunded.',
            'data' => [
                'package_id' => $packageId,
                'amount' => $amount,
                'status' => PackageOrderStatus::Failed->value,
                'order_id' => $order->id,
                'transaction_no' => $debitTransaction->transaction_no,
                'refund_transaction_no' => $refund?->transaction_no,
            ],
        ]);
    }

    /**
     * @return array{http_status: int, body: array<string, mixed>}
     */
    protected function insufficientBalanceResponse(Package $package, int $amount): array
    {
        return $this->response(422, [
            'success' => false,
            'message' => 'Insufficient wallet balance.',
            'errors' => [
                'amount' => ['Insufficient wallet balance.'],
            ],
            'data' => [
                'package_id' => $package->id,
                'amount' => $amount,
            ],
        ]);
    }

    /**
     * @return array{http_status: int, body: array<string, mixed>}
     */
    protected function conflict(string $message): array
    {
        return $this->response(409, [
            'success' => false,
            'message' => $message,
        ]);
    }

    /**
     * @return array{package_id: int, price: int, term_months: int}
     */
    protected function snapshot(Package $package, int $amount, int $termMonths): array
    {
        return [
            'package_id' => $package->id,
            'price' => $amount,
            'term_months' => $termMonths,
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{http_status: int, body: array<string, mixed>}
     */
    protected function response(int $httpStatus, array $body): array
    {
        return [
            'http_status' => $httpStatus,
            'body' => $body,
        ];
    }
}
