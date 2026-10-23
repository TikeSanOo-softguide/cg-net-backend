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
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class BuyPackageService
{
    public function __construct(
        protected PackageActivationServiceInterface $activationService,
        protected LedgerPoster $ledger,
        protected CgNetProductCatalog $catalog,
    ) {}

    /**
     * Debit and the package order commit together.
     * Activation runs only after that commit, outside any database transaction.
     * Only a definitive provider rejection refunds the debit. Ambiguous outcomes stay pending.
     *
     * @return array{http_status: int, body: array<string, mixed>}
     */
    public function buy(
        User $user,
        string $productId,
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
            return $this->existingResponse($existing, $productId, $wallet, $user);
        }

        try {
            $product = $this->catalog->find($productId);
        } catch (RuntimeException) {
            return $this->response(503, [
                'success' => false,
                'message' => 'Package catalog is temporarily unavailable. No payment was taken.',
            ]);
        }

        if (! $product) {
            throw ValidationException::withMessages([
                'product_id' => ['The selected product is unavailable.'],
            ]);
        }

        $productPrice = (float) $product['productPrice'];
        $amount = (int) $productPrice;
        $termCycle = (int) $product['monthCycle'];
        $termCycleUnit = (string) $product['monthCycleUnit'];

        if (
            $amount < 1
            || $termCycle < 1
            || ! in_array($termCycleUnit, ['月', '天'], true)
            || $productPrice !== (float) $amount
        ) {
            throw ValidationException::withMessages([
                'product_id' => ['The selected product has invalid purchase details.'],
            ]);
        }

        if ($wallet->balance < $amount) {
            return $this->insufficientBalanceResponse($productId, $amount);
        }

        try {
            $purchased = $this->debitWalletForPackage(
                wallet: $wallet,
                user: $user,
                product: $product,
                amount: $amount,
                termCycle: $termCycle,
                termCycleUnit: $termCycleUnit,
                idempotencyKey: $idempotencyKey,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );
        } catch (UniqueConstraintViolationException) {
            return $this->replayFromKey($wallet, $user, $idempotencyKey, $productId);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        if ($purchased === null) {
            return $this->replayFromKey($wallet, $user, $idempotencyKey, $productId);
        }

        /** @var PackageOrder $order */
        $order = $purchased['order'];
        /** @var LedgerTransaction $debitTransaction */
        $debitTransaction = $purchased['transaction'];

        try {
            $result = $this->activationService->activate($user, new Package, $order);
        } catch (Throwable $exception) {
            report(new RuntimeException('CG-Net activation returned an unresolved outcome.'));
            $this->markPendingVerification($order, null, 'The provider did not confirm the activation.');

            return $this->pendingResponse($productId, $amount, $order, $debitTransaction);
        }

        if ($result->success && $this->activationSucceeded($result, $order)) {
            try {
                $this->recordActivationResult($order, $result);
                $customerPackage = $this->completePurchase(
                    $order,
                    $debitTransaction,
                    $user,
                    $termCycle,
                    $termCycleUnit,
                    $result,
                );
            } catch (Throwable) {
                report(new RuntimeException('CG-Net activation succeeded but local completion needs reconciliation.'));
                $this->markPendingVerification($order, 0, 'Provider success is awaiting local reconciliation.');

                return $this->response(500, [
                    'success' => false,
                    'message' => 'Activation is confirmed and purchase completion is pending reconciliation.',
                    'data' => [
                        'product_id' => $productId,
                        'amount' => $amount,
                        'status' => PackageOrderStatus::PendingVerification->value,
                        'order_no' => $order->order_no,
                        'order_id' => $order->id,
                        'transaction_no' => $debitTransaction->transaction_no,
                    ],
                ]);
            }
        } elseif ($result->definitiveFailure) {
            $this->markFailedBeforeRefund($order, $result);
            $this->refundDebit($order, $wallet, $user, $debitTransaction);

            return $this->failureResponse($productId, $amount, $order, $debitTransaction);
        } else {
            $this->markPendingVerification(
                $order,
                $result->errorCode,
                $result->message ?? 'The provider did not confirm the activation.',
            );

            return $this->pendingResponse($productId, $amount, $order, $debitTransaction);
        }

        $order->refresh();
        $debitTransaction->refresh();

        return $this->response(200, [
            'success' => true,
            'message' => 'Package purchased successfully.',
            'data' => $this->successData($productId, $amount, $order, $debitTransaction, $customerPackage),
        ]);
    }

    /**
     * @return array{order: PackageOrder, transaction: LedgerTransaction}|null
     */
    protected function debitWalletForPackage(
        Wallet $wallet,
        User $user,
        array $product,
        int $amount,
        int $termCycle,
        string $termCycleUnit,
        string $idempotencyKey,
        ?string $ipAddress,
        ?string $userAgent,
    ): ?array {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return DB::transaction(function () use (
                    $wallet,
                    $user,
                    $product,
                    $amount,
                    $termCycle,
                    $termCycleUnit,
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

                    $account = $this->newUniqueAccount();
                    $orderNo = 'CGN-'.strtoupper((string) Str::uuid());
                    $password = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

                    if (preg_match('/^G\d{7}$/D', $account) !== 1 || preg_match('/^\d{6}$/D', $password) !== 1) {
                        throw new RuntimeException('Generated activation credentials have an invalid format.');
                    }

                    $order = PackageOrder::query()->create([
                        'user_id' => $user->id,
                        'package_id' => null,
                        'ledger_transaction_id' => $transaction->id,
                        'status' => PackageOrderStatus::Processing,
                        'order_no' => $orderNo,
                        'account' => $account,
                        'activation_password' => $password,
                        'product_id' => $product['productID'],
                        'product_price' => $product['productPrice'],
                        'snapshot' => $this->snapshot($product, $amount, $termCycle, $termCycleUnit),
                    ]);

                    return [
                        'order' => $order,
                        'transaction' => $transaction,
                    ];
                });
            } catch (UniqueConstraintViolationException $exception) {
                if (
                    LedgerTransaction::query()
                        ->where('wallet_id', $wallet->id)
                        ->where('idempotency_key', $idempotencyKey)
                        ->exists()
                ) {
                    return null;
                }

                if ($attempt === 4) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('Unable to reserve a unique package activation account.');
    }

    protected function newUniqueAccount(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $account = 'G'.str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);

            $usedInOrders = PackageOrder::query()->where('account', $account)->exists();
            $usedByCustomer = CustomerPackage::query()->where('username', $account)->exists();

            if (! $usedInOrders && ! $usedByCustomer) {
                return $account;
            }
        }

        throw new RuntimeException('Unable to reserve a unique package activation account.');
    }

    protected function completePurchase(
        PackageOrder $order,
        LedgerTransaction $debitTransaction,
        User $user,
        int $termCycle,
        string $termCycleUnit,
        PackageActivationResult $result,
    ): CustomerPackage {
        if (
            $termCycle < 1
            || ! in_array($termCycleUnit, ['月', '天'], true)
            || blank($result->username)
            || blank($result->password)
        ) {
            throw new RuntimeException('Package purchase could not be completed.');
        }

        return DB::transaction(function () use ($order, $debitTransaction, $user, $termCycle, $termCycleUnit, $result) {
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
                ! in_array(
                    $lockedOrder->status,
                    [PackageOrderStatus::Processing, PackageOrderStatus::PendingVerification],
                    true,
                )
                || $lockedDebit->status !== LedgerTransactionStatus::Processing
            ) {
                throw new RuntimeException('Package purchase could not be completed.');
            }

            $startsAt = now();
            $expiresAt = match ($termCycleUnit) {
                '月' => $startsAt->copy()->addMonths($termCycle),
                '天' => $startsAt->copy()->addDays($termCycle),
            };

            $customerPackage = CustomerPackage::query()->create([
                'user_id' => $user->id,
                'package_id' => null,
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
                'failure_reason' => null,
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
                    idempotencyKey: 'refund:'.$lockedDebit->idempotency_key,
                    actorType: WalletActorType::System,
                    actorId: $user->id,
                    reversalOf: $lockedDebit->id,
                );
            } catch (UniqueConstraintViolationException) {
                $refundTransaction = LedgerTransaction::query()
                    ->where('idempotency_key', 'refund:'.$lockedDebit->idempotency_key)
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

    protected function activationSucceeded(PackageActivationResult $result, PackageOrder $order): bool
    {
        return $result->success
            && $result->username === $order->account
            && $result->password === $order->activation_password
            && preg_match('/^G\d{7}$/D', (string) $result->username) === 1
            && preg_match('/^\d{6}$/D', (string) $result->password) === 1;
    }

    protected function recordActivationResult(PackageOrder $order, PackageActivationResult $result): void
    {
        DB::transaction(function () use ($order, $result): void {
            /** @var PackageOrder $locked */
            $locked = PackageOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PackageOrderStatus::Completed) {
                return;
            }

            $locked->update([
                'activation_result_code' => $result->errorCode ?? 0,
                'external_response' => ['err_code' => $result->errorCode ?? 0],
                'failure_reason' => null,
            ]);
        });
    }

    protected function markPendingVerification(PackageOrder $order, ?int $errorCode, string $reason): void
    {
        DB::transaction(function () use ($order, $errorCode, $reason): void {
            /** @var PackageOrder $locked */
            $locked = PackageOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PackageOrderStatus::Completed || $locked->status === PackageOrderStatus::Failed) {
                return;
            }

            $locked->update([
                'status' => PackageOrderStatus::PendingVerification,
                'activation_result_code' => $errorCode,
                'external_response' => $errorCode === null ? null : ['err_code' => $errorCode],
                'failure_reason' => mb_substr($reason, 0, 255),
            ]);
        });
    }

    protected function markFailedBeforeRefund(PackageOrder $order, PackageActivationResult $result): void
    {
        $order->update([
            'activation_result_code' => $result->errorCode,
            'external_response' => $result->errorCode === null ? null : ['err_code' => $result->errorCode],
            'failure_reason' => mb_substr($result->message ?? 'Provider rejected the activation.', 0, 255),
        ]);
    }

    /**
     * @return array{http_status: int, body: array<string, mixed>}
     */
    protected function replayFromKey(
        Wallet $wallet,
        User $user,
        string $idempotencyKey,
        string $productId,
    ): array {
        $existing = LedgerTransaction::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if (! $existing || (int) $existing->wallet_id !== (int) $wallet->id) {
            return $this->conflict('This idempotency key was already used.');
        }

        return $this->existingResponse($existing, $productId, $wallet, $user);
    }

    /**
     * @return array{http_status: int, body: array<string, mixed>}
     */
    protected function existingResponse(
        LedgerTransaction $existing,
        string $productId,
        Wallet $wallet,
        User $user,
    ): array {
        if ($existing->type !== LedgerTransactionType::WifiPackage) {
            return $this->conflict('This idempotency key was already used.');
        }

        $order = PackageOrder::query()
            ->where('ledger_transaction_id', $existing->id)
            ->first();

        if (! $order) {
            return $this->conflict('This idempotency key was already used.');
        }

        if ((string) ($order->snapshot['product_id'] ?? '') !== $productId) {
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
                'data' => $this->successData($productId, (int) $existing->amount, $order, $existing, $customerPackage),
            ]);
        }

        if ($order->activation_result_code === 0 && $order->account && $order->activation_password) {
            try {
                $customerPackage = $this->completePurchase(
                    $order,
                    $existing,
                    $order->user,
                    (int) ($order->snapshot['term_cycle'] ?? $order->snapshot['term_months'] ?? 0),
                    (string) ($order->snapshot['term_cycle_unit'] ?? $order->snapshot['month_cycle_unit'] ?? '月'),
                    PackageActivationResult::success($order->account, $order->activation_password),
                );

                $order->refresh();
                $existing->refresh();

                return $this->response(200, [
                    'success' => true,
                    'message' => 'Package purchase already processed.',
                    'data' => $this->successData($productId, (int) $existing->amount, $order, $existing, $customerPackage),
                ]);
            } catch (Throwable) {
                return $this->pendingOrderResponse($productId, (int) $existing->amount, $order, $existing);
            }
        }

        if (
            in_array($order->activation_result_code, [100, 500], true)
            && $existing->status === LedgerTransactionStatus::Processing
        ) {
            $this->refundDebit($order, $wallet, $user, $existing);

            return $this->failureResponse($productId, (int) $existing->amount, $order, $existing);
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
                    'product_id' => $productId,
                    'amount' => (int) $existing->amount,
                    'status' => PackageOrderStatus::Failed->value,
                    'order_id' => $order->id,
                    'transaction_no' => $existing->transaction_no,
                    'refund_transaction_no' => $refund?->transaction_no,
                ],
            ]);
        }

        if (
            $existing->status === LedgerTransactionStatus::Processing
            && in_array($order->status, [PackageOrderStatus::Processing, PackageOrderStatus::PendingVerification], true)
        ) {
            return $this->pendingOrderResponse($productId, (int) $existing->amount, $order, $existing);
        }

        return $this->conflict('This package purchase cannot be replayed.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function successData(
        string $productId,
        int $amount,
        PackageOrder $order,
        LedgerTransaction $transaction,
        CustomerPackage $customerPackage,
    ): array {
        return [
            'product_id' => $productId,
            'amount' => $amount,
            'status' => PackageOrderStatus::Completed->value,
            'order_id' => $order->id,
            'order_no' => $order->order_no,
            'package' => [
                'product_id' => $productId,
                'name' => $order->snapshot['product_name'] ?? null,
                'price' => $order->product_price,
                'product_line' => $order->snapshot['product_line'] ?? null,
                'product_speed' => $order->snapshot['product_speed'] ?? null,
                'month_cycle' => $order->snapshot['term_cycle'] ?? $order->snapshot['term_months'] ?? null,
                'month_cycle_unit' => $order->snapshot['term_cycle_unit'] ?? $order->snapshot['month_cycle_unit'] ?? '月',
            ],
            'transaction_no' => $transaction->transaction_no,
            'customer_package_id' => $customerPackage->id,
            'credentials' => [
                'account' => $customerPackage->username,
                'password' => $customerPackage->password,
            ],
            'starts_at' => $customerPackage->starts_at?->toISOString(),
            'expires_at' => $customerPackage->expires_at?->toISOString(),
        ];
    }

    /**
     * @return array{http_status: int, body: array<string, mixed>}
     */
    protected function failureResponse(
        string $productId,
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
                'product_id' => $productId,
                'amount' => $amount,
                'status' => PackageOrderStatus::Failed->value,
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'transaction_no' => $debitTransaction->transaction_no,
                'refund_transaction_no' => $refund?->transaction_no,
            ],
        ]);
    }

    /**
     * @return array{http_status: int, body: array<string, mixed>}
     */
    protected function pendingResponse(
        string $productId,
        int $amount,
        PackageOrder $order,
        LedgerTransaction $debitTransaction,
    ): array {
        return $this->pendingOrderResponse($productId, $amount, $order, $debitTransaction);
    }

    /**
     * @return array{http_status: int, body: array<string, mixed>}
     */
    protected function pendingOrderResponse(
        string $productId,
        int $amount,
        PackageOrder $order,
        LedgerTransaction $debitTransaction,
    ): array {
        $order->refresh();

        $message = $order->status === PackageOrderStatus::Processing
            ? 'This package purchase is already being processed.'
            : 'Package activation is awaiting provider reconciliation. The wallet debit remains on hold.';

        return $this->response(202, [
            'success' => false,
            'message' => $message,
            'data' => [
                'product_id' => $productId,
                'amount' => $amount,
                'status' => $order->status->value,
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'transaction_no' => $debitTransaction->transaction_no,
            ],
        ]);
    }

    /**
     * @return array{http_status: int, body: array<string, mixed>}
     */
    protected function insufficientBalanceResponse(string $productId, int $amount): array
    {
        return $this->response(422, [
            'success' => false,
            'message' => 'Insufficient wallet balance.',
            'errors' => [
                'amount' => ['Insufficient wallet balance.'],
            ],
            'data' => [
                'product_id' => $productId,
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
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    protected function snapshot(array $product, int $amount, int $termCycle, string $termCycleUnit): array
    {
        return [
            'price' => $amount,
            'term_months' => $termCycleUnit === '月' ? $termCycle : null,
            'term_cycle' => $termCycle,
            'term_cycle_unit' => $termCycleUnit,
            'product_id' => $product['productID'],
            'product_name' => $product['productName'],
            'product_price' => $product['productPrice'],
            'product_line' => $product['productLine'],
            'product_speed' => $product['productSpeed'],
            'month_cycle_unit' => $termCycleUnit,
            'installation_fee' => $product['installationFee'],
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
