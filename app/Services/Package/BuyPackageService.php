<?php

namespace App\Services\Package;

use App\Exceptions\PackageOrderReviewConflict;
use App\Enums\MessageCode;
use App\Enums\CustomerPackageStatus;
use App\Enums\LedgerAccountCode;
use App\Enums\TransactionIdPrefix;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\PackageOrderNotificationEvent;
use App\Enums\PackageOrderStatus;
use App\Enums\WalletActorType;
use App\Enums\WalletStatus;
use App\Jobs\ReconcileStuckPackageOrdersJob;
use App\Models\AdminNotification;
use App\Models\CustomerPackage;
use App\Models\LedgerTransaction;
use App\Models\Package;
use App\Models\PackageOrder;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\PackageOrderStatusNotification;
use App\Services\AdminNotification\AdminNotificationDelivery;
use App\Services\Ledger\LedgerPoster;
use App\Services\Order\OrderNumberGenerator;
use App\Services\PackageActivation\PackageActivationResult;
use App\Support\ApiMessage;
use App\Services\PackageActivation\PackageActivationServiceInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class BuyPackageService
{
    public function __construct(
        protected PackageActivationServiceInterface $activationService,
        protected LedgerPoster $ledger,
        protected CgNetProductCatalog $catalog,
        protected OrderNumberGenerator $orderNumbers,
        protected AdminNotificationDelivery $adminNotificationDelivery,
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

        if (!$wallet) {
            return $this->coded(404, MessageCode::PkgWalletNotFound);
        }

        if ($wallet->status !== WalletStatus::Active) {
            return $this->coded(403, MessageCode::PkgWalletInactive);
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
            return $this->coded(503, MessageCode::PkgCatalogUnavailable);
        }

        if (!$product) {
            throw ValidationException::withMessages([
                'product_id' => ['The selected product is unavailable.'],
            ]);
        }

        $productPrice = round((float) $product['productPrice'], 2);
        $amount = $productPrice;
        $termCycle = (int) $product['monthCycle'];
        $termCycleUnit = (string) $product['monthCycleUnit'];

        if (
            $amount < 1 ||
            $termCycle < 1 ||
            !in_array($termCycleUnit, ['月', '天'], true) ||
            $productPrice !== round((float) $amount, 2)
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
            $result = $this->activationService->activate($user, new Package(), $order);
        } catch (Throwable $exception) {
            report(new RuntimeException('CG-Net activation returned an unresolved outcome.'));
            $this->leaveProcessing(
                $order,
                $debitTransaction,
                null,
                'The provider did not confirm the activation.',
                notifyUser: true,
                externalResponse: [
                    'outcome' => 'unknown',
                    'message' => 'The provider did not confirm the activation.',
                    'exception' => $exception->getMessage(),
                ],
            );

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
                $this->leaveProcessing(
                    $order,
                    $debitTransaction,
                    0,
                    'Provider success is awaiting local reconciliation.',
                    notifyUser: true,
                    externalResponse: $this->activationExternalResponse($result),
                );

                return $this->coded(500, MessageCode::PkgCompletionPending, [
                    'data' => [
                        'product_id' => $productId,
                        'amount' => $amount,
                        'status' => PackageOrderStatus::Processing->value,
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
            $this->leaveProcessing(
                $order,
                $debitTransaction,
                $result->errorCode,
                $result->message ?? 'The provider did not confirm the activation.',
                notifyUser: true,
                externalResponse: $this->activationExternalResponse($result),
            );

            return $this->pendingResponse($productId, $amount, $order, $debitTransaction);
        }

        $order->refresh();
        $debitTransaction->refresh();

        return $this->coded(200, MessageCode::PkgPurchased, [
            'data' => $this->successData($productId, $amount, $order, $debitTransaction, $customerPackage),
        ]);
    }

    public function reconcile(LedgerTransaction $transaction): void
    {
        $transaction->refresh()->loadMissing(['packageOrder', 'wallet.user']);

        if (
            $transaction->type !== LedgerTransactionType::WifiPackage ||
            $transaction->status !== LedgerTransactionStatus::Processing
        ) {
            return;
        }

        $order = $transaction->packageOrder;
        if (
            $order === null ||
            !in_array($order->status, [PackageOrderStatus::Processing, PackageOrderStatus::Review], true)
        ) {
            return;
        }

        $graceSeconds = (int) config('services.cg_api.processing_grace_seconds', 60);
        $retrySeconds = (int) config('services.cg_api.processing_retry_seconds', 60);
        $maxAgeSeconds = (int) config('services.cg_api.processing_max_age_seconds', 900);
        if ($transaction->created_at?->gt(now()->subSeconds($graceSeconds))) {
            return;
        }

        if ($transaction->created_at?->lte(now()->subSeconds($maxAgeSeconds))) {
            $this->markReview(
                $order,
                $order->activation_result_code,
                'Automatic retries stopped; manual review is required.',
            );
            Log::warning('Package activation reconciliation reached its maximum age.', [
                'ledger_transaction_id' => $transaction->id,
                'package_order_id' => $order->id,
                'order_no' => $order->order_no,
            ]);

            return;
        }

        try {
            $result = $this->activationService->activate($order->user, new Package(), $order);
        } catch (Throwable $exception) {
            report(new RuntimeException('CG-Net package activation reconciliation returned an unresolved outcome.'));
            $this->leaveProcessing(
                $order,
                $transaction,
                null,
                'The provider did not confirm the activation.',
                $retrySeconds,
                externalResponse: [
                    'outcome' => 'unknown',
                    'message' => 'The provider did not confirm the activation.',
                    'exception' => $exception->getMessage(),
                ],
            );

            return;
        }

        if ($result->success && $this->activationSucceeded($result, $order)) {
            try {
                $this->recordActivationResult($order, $result);
                $this->completePurchase(
                    $order,
                    $transaction,
                    $order->user,
                    (int) ($order->snapshot['term_cycle'] ?? ($order->snapshot['term_months'] ?? 0)),
                    (string) ($order->snapshot['term_cycle_unit'] ?? ($order->snapshot['month_cycle_unit'] ?? '月')),
                    $result,
                );
            } catch (Throwable $exception) {
                report(new RuntimeException('Confirmed package activation still needs local reconciliation.'));
                $this->leaveProcessing(
                    $order,
                    $transaction,
                    $result->errorCode ?? 0,
                    'Provider success is awaiting local reconciliation.',
                    $retrySeconds,
                    externalResponse: $this->activationExternalResponse($result),
                );
            }

            return;
        }

        if ($result->definitiveFailure) {
            $wallet = $transaction->wallet;
            $user = $wallet?->user;

            if ($wallet === null || $user === null) {
                Log::error('Unable to refund rejected package activation during reconciliation.', [
                    'ledger_transaction_id' => $transaction->id,
                    'package_order_id' => $order->id,
                    'wallet_id' => $transaction->wallet_id,
                ]);
                $this->markReview(
                    $order,
                    $result->errorCode,
                    'Provider rejected activation, but refund owner is unavailable.',
                );

                return;
            }

            $this->markFailedBeforeRefund($order, $result);
            $this->refundDebit($order, $wallet, $user, $transaction);

            return;
        }

        $this->leaveProcessing(
            $order,
            $transaction,
            $result->errorCode,
            $result->message ?? 'The provider did not confirm the activation.',
            $retrySeconds,
            externalResponse: $this->activationExternalResponse($result),
        );
    }

    public function handleReconciliationFailure(LedgerTransaction $transaction, Throwable $exception): void
    {
        Log::error('Failed to reconcile package activation.', [
            'ledger_transaction_id' => $transaction->id,
            'exception' => $exception->getMessage(),
        ]);

        $transaction->refresh()->loadMissing('packageOrder');
        $order = $transaction->packageOrder;

        if (
            $transaction->status !== LedgerTransactionStatus::Processing ||
            $order === null ||
            !in_array($order->status, [PackageOrderStatus::Processing, PackageOrderStatus::Review], true)
        ) {
            return;
        }

        $this->markReview($order, $order->activation_result_code, 'Package activation reconciliation failed.');
    }

    public function resolveReviewSuccessfully(PackageOrder $order, int $adminId): void
    {
        $order->loadMissing(['ledgerTransaction', 'user']);
        $transaction = $order->ledgerTransaction;

        if (
            $transaction === null ||
            $transaction->type !== LedgerTransactionType::WifiPackage ||
            $transaction->status !== LedgerTransactionStatus::Review ||
            $order->status !== PackageOrderStatus::Review
        ) {
            throw new PackageOrderReviewConflict('The package order is no longer available for review.');
        }

        if (blank($order->account) || blank($order->activation_password)) {
            throw new PackageOrderReviewConflict('The package activation credentials are unavailable.');
        }

        $this->completePurchase(
            $order,
            $transaction,
            $order->user,
            (int) ($order->snapshot['term_cycle'] ?? ($order->snapshot['term_months'] ?? 0)),
            (string) ($order->snapshot['term_cycle_unit'] ?? ($order->snapshot['month_cycle_unit'] ?? '月')),
            PackageActivationResult::success($order->account, $order->activation_password),
            resolvedBy: $adminId,
        );
    }

    public function refundReview(PackageOrder $order, int $adminId): LedgerTransaction
    {
        $order->loadMissing(['ledgerTransaction.wallet.user', 'user']);
        $transaction = $order->ledgerTransaction;
        $wallet = $transaction?->wallet;
        $user = $wallet?->user;

        if (
            $transaction === null ||
            $transaction->type !== LedgerTransactionType::WifiPackage ||
            $transaction->status !== LedgerTransactionStatus::Review ||
            $order->status !== PackageOrderStatus::Review ||
            $wallet === null ||
            $user === null
        ) {
            throw new PackageOrderReviewConflict('The package order is no longer available for review.');
        }

        return $this->refundDebit(
            $order,
            $wallet,
            $user,
            $transaction,
            [
                'phase' => 'manual_review_refunded',
                'resolved_by' => $adminId,
                'resolved_at' => now()->toISOString(),
            ],
        );
    }

    protected function leaveProcessing(
        PackageOrder $order,
        LedgerTransaction $transaction,
        ?int $errorCode,
        string $reason,
        ?int $delaySeconds = null,
        bool $notifyUser = false,
        ?array $externalResponse = null,
    ): void {
        $shouldSchedule = DB::transaction(function () use ($order, $errorCode, $reason, $externalResponse): bool {
            /** @var PackageOrder $locked */
            $locked = PackageOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PackageOrderStatus::Completed || $locked->status === PackageOrderStatus::Failed) {
                return false;
            }

            $locked->update([
                'activation_result_code' => $errorCode,
                'external_response' => $externalResponse ?? [
                    'outcome' => 'unknown',
                    'message' => $reason,
                    'err_code' => $errorCode,
                ],
                'failure_reason' => mb_substr($reason, 0, 255),
            ]);

            return true;
        });

        if (!$shouldSchedule) {
            return;
        }

        $delaySeconds ??= (int) config('services.cg_api.processing_grace_seconds', 60);
        ReconcileStuckPackageOrdersJob::dispatch($transaction->id)->delay(now()->addSeconds(max(1, $delaySeconds)));

        if ($notifyUser) {
            $this->notifyUser(
                $transaction->wallet?->user,
                PackageOrderNotificationEvent::Processing,
                $order,
                $transaction,
            );
        }
    }

    /**
     * @return array{order: PackageOrder, transaction: LedgerTransaction}|null
     */
    protected function debitWalletForPackage(
        Wallet $wallet,
        User $user,
        array $product,
        float $amount,
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
                        transactionIdPrefix: TransactionIdPrefix::BuyPackage,
                    );

                    $ownsThisPurchase =
                        (int) $transaction->wallet_id === (int) $wallet->id &&
                        $transaction->type === LedgerTransactionType::WifiPackage &&
                        $transaction->idempotency_key === $idempotencyKey &&
                        $transaction->status === LedgerTransactionStatus::Processing;

                    $alreadyLinked = PackageOrder::query()->where('ledger_transaction_id', $transaction->id)->exists();

                    if (!$ownsThisPurchase || $alreadyLinked) {
                        return null;
                    }

                    $account = $this->newUniqueAccount();
                    $orderNo = $this->orderNumbers->generate();
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
            $account = 'G' . str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);

            $usedInOrders = PackageOrder::query()->where('account', $account)->exists();
            $usedByCustomer = CustomerPackage::query()->where('username', $account)->exists();

            if (!$usedInOrders && !$usedByCustomer) {
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
        ?int $resolvedBy = null,
    ): CustomerPackage {
        if (
            $termCycle < 1 ||
            !in_array($termCycleUnit, ['月', '天'], true) ||
            blank($result->username) ||
            blank($result->password)
        ) {
            throw new RuntimeException('Package purchase could not be completed.');
        }

        $completion = DB::transaction(function () use (
            $order,
            $debitTransaction,
            $user,
            $termCycle,
            $termCycleUnit,
            $result,
            $resolvedBy,
        ) {
            /** @var PackageOrder $lockedOrder */
            $lockedOrder = PackageOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            /** @var LedgerTransaction $lockedDebit */
            $lockedDebit = LedgerTransaction::query()->whereKey($debitTransaction->id)->lockForUpdate()->firstOrFail();

            $existingPackage = CustomerPackage::query()->where('package_order_id', $lockedOrder->id)->first();

            if (
                $resolvedBy !== null &&
                (
                    $lockedOrder->status !== PackageOrderStatus::Review ||
                    $lockedDebit->status !== LedgerTransactionStatus::Review ||
                    $existingPackage !== null
                )
            ) {
                throw new PackageOrderReviewConflict('The package order is no longer available for review.');
            }

            if ($existingPackage) {
                return ['customer_package' => $existingPackage, 'completed' => false];
            }

            if (
                !in_array($lockedOrder->status, [PackageOrderStatus::Processing, PackageOrderStatus::Review], true) ||
                (
                    $lockedDebit->status !== LedgerTransactionStatus::Processing &&
                    !($resolvedBy !== null && $lockedDebit->status === LedgerTransactionStatus::Review)
                )
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

            $orderAttributes = [
                'status' => PackageOrderStatus::Completed,
                'completed_at' => $startsAt,
                'ledger_transaction_id' => $lockedDebit->id,
                'failure_reason' => null,
            ];

            if ($resolvedBy !== null) {
                $externalResponse = is_array($lockedOrder->external_response) ? $lockedOrder->external_response : [];
                $orderAttributes['external_response'] = [
                    ...$externalResponse,
                    'phase' => 'manual_review_completed',
                    'resolved_by' => $resolvedBy,
                    'resolved_at' => $startsAt->toISOString(),
                ];
            }

            $lockedOrder->update($orderAttributes);

            $lockedDebit->update(['status' => LedgerTransactionStatus::Completed]);

            return ['customer_package' => $customerPackage, 'completed' => true];
        });

        if ($completion['completed']) {
            $this->notifyUser(
                $user,
                PackageOrderNotificationEvent::Completed,
                $order->refresh(),
                $debitTransaction->refresh(),
            );
        }

        return $completion['customer_package'];
    }

    protected function refundDebit(
        PackageOrder $order,
        Wallet $wallet,
        User $user,
        LedgerTransaction $debitTransaction,
        ?array $reviewResolution = null,
    ): LedgerTransaction {
        $refund = DB::transaction(function () use ($order, $wallet, $user, $debitTransaction, $reviewResolution) {
            /** @var PackageOrder $lockedOrder */
            $lockedOrder = PackageOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            /** @var LedgerTransaction $lockedDebit */
            $lockedDebit = LedgerTransaction::query()->whereKey($debitTransaction->id)->lockForUpdate()->firstOrFail();

            if (
                $reviewResolution !== null &&
                (
                    $lockedOrder->status !== PackageOrderStatus::Review ||
                    $lockedDebit->status !== LedgerTransactionStatus::Review
                )
            ) {
                throw new PackageOrderReviewConflict('The package order is no longer available for review.');
            }

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

                return ['transaction' => $existingRefund, 'created' => false];
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

                return ['transaction' => $existingRefund, 'created' => false];
            }

            $created = true;
            try {
                $refundTransaction = $this->ledger->creditWallet(
                    wallet: $wallet,
                    amount: (float) $lockedDebit->amount,
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
                $created = false;
            }

            $lockedDebit->update(['status' => LedgerTransactionStatus::Failed]);
            $this->markOrderFailed($lockedOrder);

            if ($reviewResolution !== null) {
                $externalResponse = is_array($lockedOrder->external_response) ? $lockedOrder->external_response : [];
                $lockedOrder->update([
                    'external_response' => [...$externalResponse, ...$reviewResolution],
                ]);
            }

            return ['transaction' => $refundTransaction->refresh(), 'created' => $created];
        });

        if ($refund['created']) {
            $this->notifyUser(
                $user,
                PackageOrderNotificationEvent::Refunded,
                $order->refresh(),
                $debitTransaction->refresh(),
                $refund['transaction']->transaction_no,
            );
        }

        return $refund['transaction'];
    }

    protected function notifyUser(
        ?User $user,
        PackageOrderNotificationEvent $event,
        PackageOrder $order,
        LedgerTransaction $transaction,
        ?string $refundTransactionNo = null,
    ): void {
        if ($user === null) {
            return;
        }

        try {
            $user->notify(
                new PackageOrderStatusNotification(
                    event: $event,
                    orderId: $order->id,
                    orderNo: $order->order_no,
                    transactionNo: $transaction->transaction_no,
                    amount: (string) $transaction->amount,
                    productId: $order->product_id,
                    productName: (string) ($order->snapshot['product_name'] ?? $order->product_id),
                    refundTransactionNo: $refundTransactionNo,
                ),
            );
        } catch (Throwable $exception) {
            Log::error('Unable to queue package order status notification.', [
                'user_id' => $user->id,
                'package_order_id' => $order->id,
                'event' => $event->value,
                'exception' => $exception->getMessage(),
            ]);
        }
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
        return $result->success &&
            $result->username === $order->account &&
            $result->password === $order->activation_password &&
            preg_match('/^G\d{7}$/D', (string) $result->username) === 1 &&
            preg_match('/^\d{6}$/D', (string) $result->password) === 1;
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
                'external_response' => $this->activationExternalResponse($result),
                'failure_reason' => null,
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    protected function activationExternalResponse(PackageActivationResult $result): array
    {
        return $result->externalResponse ??
            array_filter(
                [
                    'outcome' => $result->success ? 'success' : ($result->definitiveFailure ? 'rejected' : 'unknown'),
                    'message' => $result->message,
                    'err_code' => $result->errorCode ?? ($result->success ? 0 : null),
                ],
                fn($value) => $value !== null,
            );
    }

    protected function markReview(PackageOrder $order, ?int $errorCode, string $reason): void
    {
        $reviewedOrderId = DB::transaction(function () use ($order, $errorCode, $reason): ?int {
            /** @var PackageOrder $locked */
            $locked = PackageOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            /** @var LedgerTransaction $lockedTransaction */
            $lockedTransaction = LedgerTransaction::query()
                ->whereKey($locked->ledger_transaction_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                in_array($locked->status, [PackageOrderStatus::Completed, PackageOrderStatus::Failed], true) ||
                !in_array(
                    $lockedTransaction->status,
                    [LedgerTransactionStatus::Processing, LedgerTransactionStatus::Review],
                    true,
                )
            ) {
                return null;
            }

            $shouldNotify = $locked->status !== PackageOrderStatus::Review;
            $externalResponse = is_array($locked->external_response) ? $locked->external_response : [];

            $locked->update([
                'status' => PackageOrderStatus::Review,
                'activation_result_code' => $errorCode,
                'external_response' => [
                    ...$externalResponse,
                    'phase' => 'manual_review',
                    'manual_review_reason' => $reason,
                ],
                'failure_reason' => mb_substr($reason, 0, 255),
            ]);
            $lockedTransaction->update(['status' => LedgerTransactionStatus::Review]);

            return $shouldNotify ? $locked->id : null;
        });

        if ($reviewedOrderId === null) {
            return;
        }

        $notification = AdminNotification::query()->create([
            'type' => 'other',
            'title' => 'Package order requires manual review',
            'body' => sprintf(
                'Package order %s could not be completed automatically: %s',
                $order->order_no,
                mb_substr($reason, 0, 180),
            ),
            'severity' => 'high',
            'reference_type' => 'package_order',
            'reference_id' => $reviewedOrderId,
            'sent_at' => now(),
        ]);

        $this->adminNotificationDelivery->deliver($notification, 'billing.view');
    }

    protected function markFailedBeforeRefund(PackageOrder $order, PackageActivationResult $result): void
    {
        $order->update([
            'activation_result_code' => $result->errorCode,
            'external_response' => $this->activationExternalResponse($result),
            'failure_reason' => mb_substr($result->message ?? 'Provider rejected the activation.', 0, 255),
        ]);
    }

    /**
     * @return array{http_status: int, body: array<string, mixed>}
     */
    protected function replayFromKey(Wallet $wallet, User $user, string $idempotencyKey, string $productId): array
    {
        $existing = LedgerTransaction::query()->where('idempotency_key', $idempotencyKey)->first();

        if (!$existing || (int) $existing->wallet_id !== (int) $wallet->id) {
            return $this->coded(409, MessageCode::PkgKeyUsed);
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
            return $this->coded(409, MessageCode::PkgKeyUsed);
        }

        $order = PackageOrder::query()->where('ledger_transaction_id', $existing->id)->first();

        if (!$order) {
            return $this->coded(409, MessageCode::PkgKeyUsed);
        }

        if ((string) ($order->snapshot['product_id'] ?? '') !== $productId) {
            return $this->coded(409, MessageCode::PkgKeyMismatch);
        }

        if ($order->status === PackageOrderStatus::Review) {
            return $this->pendingOrderResponse($productId, $existing->amount, $order, $existing);
        }

        if (
            $existing->status === LedgerTransactionStatus::Completed &&
            $order->status === PackageOrderStatus::Completed
        ) {
            $customerPackage = $order->customerPackage;

            if (!$customerPackage) {
                return $this->coded(409, MessageCode::PkgCannotReplay);
            }

            return $this->coded(200, MessageCode::PkgAlreadyProcessed, [
                'data' => $this->successData($productId, $existing->amount, $order, $existing, $customerPackage),
            ]);
        }

        if (in_array($order->activation_result_code, [0, 1], true) && $order->account && $order->activation_password) {
            try {
                $customerPackage = $this->completePurchase(
                    $order,
                    $existing,
                    $order->user,
                    (int) ($order->snapshot['term_cycle'] ?? ($order->snapshot['term_months'] ?? 0)),
                    (string) ($order->snapshot['term_cycle_unit'] ?? ($order->snapshot['month_cycle_unit'] ?? '月')),
                    PackageActivationResult::success($order->account, $order->activation_password),
                );

                $order->refresh();
                $existing->refresh();

                return $this->coded(200, MessageCode::PkgAlreadyProcessed, [
                    'data' => $this->successData($productId, $existing->amount, $order, $existing, $customerPackage),
                ]);
            } catch (Throwable) {
                return $this->pendingOrderResponse($productId, $existing->amount, $order, $existing);
            }
        }

        if (
            in_array($order->activation_result_code, [100, 500], true) &&
            $existing->status === LedgerTransactionStatus::Processing
        ) {
            $this->refundDebit($order, $wallet, $user, $existing);

            return $this->failureResponse($productId, $existing->amount, $order, $existing);
        }

        if ($existing->status === LedgerTransactionStatus::Failed || $order->status === PackageOrderStatus::Failed) {
            $refund = LedgerTransaction::query()
                ->where('reversal_of', $existing->id)
                ->where('type', LedgerTransactionType::Refund)
                ->first();

            return $this->coded(502, MessageCode::PkgActivationRefunded, [
                'data' => [
                    'product_id' => $productId,
                    'amount' => $existing->amount,
                    'status' => PackageOrderStatus::Failed->value,
                    'order_id' => $order->id,
                    'transaction_no' => $existing->transaction_no,
                    'refund_transaction_no' => $refund?->transaction_no,
                ],
            ]);
        }

        if (
            $existing->status === LedgerTransactionStatus::Processing &&
            in_array($order->status, [PackageOrderStatus::Processing, PackageOrderStatus::Review], true)
        ) {
            return $this->pendingOrderResponse($productId, $existing->amount, $order, $existing);
        }

        return $this->coded(409, MessageCode::PkgCannotReplay);
    }

    /**
     * @return array<string, mixed>
     */
    protected function successData(
        string $productId,
        string|int|float $amount,
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
                'product_name' => $order->snapshot['product_name'] ?? null,
                'product_price' => $order->product_price,
                'product_line' => $order->snapshot['product_line'] ?? null,
                'product_speed' => $order->snapshot['product_speed'] ?? null,
                'month_cycle' => $order->snapshot['term_cycle'] ?? ($order->snapshot['term_months'] ?? null),
                'month_cycle_unit' =>
                    $order->snapshot['term_cycle_unit'] ?? ($order->snapshot['month_cycle_unit'] ?? '月'),
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
        string|int|float $amount,
        PackageOrder $order,
        LedgerTransaction $debitTransaction,
    ): array {
        $order->refresh();

        $refund = LedgerTransaction::query()
            ->where('reversal_of', $debitTransaction->id)
            ->where('type', LedgerTransactionType::Refund)
            ->first();

        return $this->coded(502, MessageCode::PkgActivationRefunded, [
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
        string|int|float $amount,
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
        float $amount,
        PackageOrder $order,
        LedgerTransaction $debitTransaction,
    ): array {
        $order->refresh();

        $code = match ($order->status) {
            PackageOrderStatus::Processing => MessageCode::PkgProcessing,
            PackageOrderStatus::Review => MessageCode::PkgManualReview,
            default => MessageCode::PkgReconciliationPending,
        };

        return $this->coded(202, $code, [
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
    protected function insufficientBalanceResponse(string $productId, float $amount): array
    {
        return $this->coded(422, MessageCode::PkgInsufficientBalance, [
            'errors' => [
                'amount' => [MessageCode::PkgInsufficientBalance->message()],
            ],
            'data' => [
                'product_id' => $productId,
                'amount' => $amount,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{http_status: int, body: array<string, mixed>}
     */
    protected function coded(int $httpStatus, MessageCode $code, array $body = []): array
    {
        $success = $httpStatus >= 200 && $httpStatus < 300 && $httpStatus !== 202;

        return $this->response($httpStatus, ApiMessage::attach($code, array_merge(['success' => $success], $body)));
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    protected function snapshot(array $product, float $amount, int $termCycle, string $termCycleUnit): array
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
