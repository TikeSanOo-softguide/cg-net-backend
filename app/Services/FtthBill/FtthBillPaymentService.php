<?php

namespace App\Services\FtthBill;

use App\Enums\MessageCode;
use App\Enums\BillPaymentNotificationEvent;
use App\Enums\BillPaymentStatus;
use App\Enums\LedgerAccountCode;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\WalletActorType;
use App\Enums\WalletStatus;
use App\Jobs\ReconcileStuckFtthBillPaymentsJob;
use App\Models\BillPayment;
use App\Models\AdminNotification;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\FtthBillPaymentStatusNotification;
use App\Services\Billing\BillingServerClient;
use App\Services\BroarbandAccount\AccountInfoService;
use App\Services\BroarbandAccount\BroadbandAccountService;
use App\Services\CgApiTokenException;
use App\Services\CgNetApiClient;
use App\Services\Ledger\LedgerPoster;
use App\Services\AdminNotification\AdminNotificationDelivery;
use App\Support\ApiMessage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use RuntimeException;

class FtthBillPaymentService
{
    private const RECHARGE_REJECTED_MESSAGE = 'CG-Net rejected the recharge request.';
    private const RECHARGE_UNKNOWN_MESSAGE = 'CG-Net did not confirm the recharge.';
    private const RECONCILIATION_DETAILS_INCOMPLETE_MESSAGE = 'Stored recharge details are incomplete and cannot be reconciled.';
    private const RECONCILIATION_DETAILS_CHANGED_MESSAGE = 'Account recharge details changed or are unavailable during reconciliation.';
    private const RECONCILIATION_STATUS_UNAVAILABLE_MESSAGE = 'Payment status could not be checked during reconciliation.';
    private const PAYMENT_TIMEOUT_MESSAGE = 'Payment timed out waiting for billing confirmation.';
    private const RECHARGE_NO_RESPONSE_MESSAGE = 'CG-Net recharge request did not receive a response.';
    private const RECHARGE_INVALID_RESPONSE_MESSAGE = 'CG-Net returned an invalid recharge response.';

    public function __construct(
        protected CgNetApiClient $cgNet,
        protected AccountInfoService $accountInfo,
        protected BillingServerClient $billing,
        protected LedgerPoster $ledger,
        protected BroadbandAccountService $broadbandAccounts,
        protected AdminNotificationDelivery $adminNotificationDelivery,
    ) {}

    /**
     * @return array{
     *     http_status: int,
     *     body: array<string, mixed>
     * }
     */
    public function pay(
        User $user,
        string $accountNumber,
        string $serviceType,
        string $idempotencyKey,
        ?string $remark = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        $wallet = $user->wallet;

        if (!$wallet) {
            return $this->coded(404, MessageCode::FtthWalletNotFound);
        }

        if ($wallet->status !== WalletStatus::Active) {
            return $this->coded(403, MessageCode::FtthWalletInactive);
        }

        $accountNumber = trim($accountNumber);

        if ($accountNumber === '') {
            return $this->accountMissing();
        }

        $existing = LedgerTransaction::query()
            ->with('billPayment')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            if (
                (int) $existing->wallet_id !== (int) $wallet->id ||
                $existing->type !== LedgerTransactionType::FtthBill
            ) {
                return $this->coded(409, MessageCode::FtthCannotReplay);
            }

            return $this->existingResponse($existing, $accountNumber, $serviceType);
        }

        try {
            $accountInfo = $this->accountInfo->find($serviceType, $accountNumber);
        } catch (ConnectionException | RuntimeException) {
            return $this->coded(502, MessageCode::FtthBillingFailed);
        }

        if ($accountInfo === null) {
            return $this->accountMissing();
        }

        $productId = $accountInfo['productID'] ?? null;
        $productPrice = $accountInfo['productPrice'] ?? null;
        $product = $this->normalizeProductDetails($productId, $productPrice);

        if ($product === null) {
            return $this->coded(502, MessageCode::FtthBillingFailed);
        }

        $productId = $product['product_id'];
        $amount = $product['product_price'];
        $orderNo = 'FTTH-' . strtoupper((string) Str::uuid());

        if ($wallet->balance < $amount) {
            return $this->coded(422, MessageCode::FtthInsufficientBalance, [
                'errors' => [
                    'amount' => [MessageCode::FtthInsufficientBalance->message()],
                ],
            ]);
        }

        try {
            $debitResult = $this->debitWallet(
                wallet: $wallet,
                user: $user,
                amount: $amount,
                serviceType: $serviceType,
                productId: $productId,
                productPrice: $amount,
                snapshot: $this->productSnapshot($accountInfo),
                orderNo: $orderNo,
                remark: $remark,
                idempotencyKey: $idempotencyKey,
                accountNumber: $accountNumber,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );

            if (!$debitResult['created']) {
                return $this->existingResponse($debitResult['transaction'], $accountNumber, $serviceType);
            }

            $pendingTransaction = $debitResult['transaction'];
        } catch (UniqueConstraintViolationException) {
            $existing = LedgerTransaction::query()
                ->with('billPayment')
                ->where('idempotency_key', $idempotencyKey)
                ->firstOrFail();

            if (
                (int) $existing->wallet_id !== (int) $wallet->id ||
                $existing->type !== LedgerTransactionType::FtthBill
            ) {
                return $this->coded(409, MessageCode::FtthCannotReplay);
            }

            return $this->existingResponse($existing, $accountNumber, $serviceType);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        $billPayment = $pendingTransaction->billPayment;
        if (!$billPayment) {
            throw new RuntimeException('FTTH payment details were not saved with the ledger debit.');
        }

        $billingResponse = $this->submitRecharge($billPayment);

        return match ($billingResponse['outcome']) {
            'success' => $this->completePayment($pendingTransaction, $accountNumber, $billingResponse),
            'rejected' => $this->failAndRefund($pendingTransaction, $wallet, $user, $accountNumber, $billingResponse),
            default => $this->leaveProcessing($pendingTransaction, $accountNumber, $billingResponse),
        };
    }

    /**
     * @return array{http_status: int, body: array<string, mixed>}
     */
    public function pendingSlip(User $user): array
    {
        $accountNumber = $user->broadband_account_number;

        if (!$accountNumber) {
            return $this->coded(422, MessageCode::FtthAccountMissing);
        }

        try {
            $slip = $this->billing->lookupPendingBillSlip($accountNumber);
        } catch (ConnectionException | RuntimeException $exception) {
            return $this->coded(502, MessageCode::FtthBillingFailed, [
                'message' => $exception->getMessage(),
            ]);
        }

        $billMonth = $slip['bill_month'];
        $billMonthLabel = Carbon::createFromFormat('!Y-m', $billMonth)->format('F Y');
        unset($slip['bill_month'], $slip['bill_month_label'], $slip['paid_slips']);

        $broadbandAccount = $this->broadbandAccounts->findByAccountNumber($accountNumber);

        return $this->coded(200, MessageCode::FtthPendingSlip, [
            'data' => [
                'bill_month' => $billMonth,
                'bill_month_label' => $billMonthLabel,
                'customer_name' => $broadbandAccount['customer_name'] ?? null,
                'payment_method' => 'CTO',
                'slip' => $slip,
            ],
        ]);
    }

    /**
     * @return array{http_status: int, body: array<string, mixed>}
     */
    public function paidSlips(User $user): array
    {
        $accountNumber = $user->broadband_account_number;

        if (!$accountNumber) {
            return $this->coded(422, MessageCode::FtthAccountMissing);
        }

        try {
            $paidSlips = $this->billing->lookupPaidBillSlips($accountNumber);
            $broadbandAccount = $this->broadbandAccounts->findByAccountNumber($accountNumber);
            $customerName = $broadbandAccount['customer_name'] ?? null;
            $paidSlips = array_map(
                fn(array $paidSlip): array => $this->formatPaidSlip($paidSlip, $accountNumber, $customerName),
                $paidSlips,
            );
            usort(
                $paidSlips,
                fn(array $first, array $second): int => strcmp($second['bill_month'], $first['bill_month']),
            );
        } catch (RuntimeException $exception) {
            return $this->coded(502, MessageCode::FtthBillingFailed, [
                'message' => $exception->getMessage(),
            ]);
        }

        return $this->coded(200, MessageCode::FtthPaidSlips, [
            'data' => [
                'paid_slips' => $paidSlips,
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $paidSlip
     * @return array<string, mixed>
     */
    protected function formatPaidSlip(array $paidSlip, string $accountNumber, ?string $customerName): array
    {
        $paidSlipData = is_array($paidSlip['data'] ?? null) ? $paidSlip['data'] : [];
        $billMonth = $paidSlip['bill_month'] ?? ($paidSlipData['bill_month'] ?? null);

        if (!is_string($billMonth) || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D', $billMonth) !== 1) {
            throw new RuntimeException('The billing server returned a paid slip without a valid bill month.');
        }

        $slip = $paidSlip['slip'] ?? ($paidSlipData['slip'] ?? null);
        if (!is_array($slip)) {
            $slip = array_intersect_key(
                [...$paidSlip, ...$paidSlipData],
                array_flip(['account_number', 'amount', 'slip_url', 'url']),
            );
        }

        $slip['account_number'] ??= $accountNumber;

        return [
            'bill_month' => $billMonth,
            'bill_month_label' => Carbon::createFromFormat('!Y-m', $billMonth)->format('F Y'),
            'customer_name' => $customerName,
            'payment_method' => 'CTO',
            'slip' => $slip,
        ];
    }

    /**
     * Re-check the account and retry RechargeAPI with the persisted OrderNo.
     */
    public function reconcile(LedgerTransaction $transaction): void
    {
        if ($transaction->type !== LedgerTransactionType::FtthBill) {
            return;
        }

        $transaction->refresh()->loadMissing(['billPayment', 'wallet.user']);

        if ($transaction->status !== LedgerTransactionStatus::Processing) {
            return;
        }

        $graceSeconds = (int) config('services.billing.processing_grace_seconds', 120);
        $maxAgeSeconds = (int) config('services.billing.processing_max_age_seconds', 3600);

        if ($transaction->created_at?->gt(now()->subSeconds($graceSeconds))) {
            return;
        }

        $accountNumber =
            (string) ($transaction->billPayment?->broadband_account_number ??
                ($transaction->billPayment?->external_response['broadband_account_number'] ??
                    ($transaction->billPayment?->external_response['account_number'] ??
                        ($transaction->wallet?->user?->broadband_account_number ?? ''))));

        $payment = $transaction->billPayment;
        try {
            if (
                $payment === null ||
                $payment->order_no === null ||
                $payment->service_type === null ||
                $payment->product_id === null ||
                $payment->product_price === null
            ) {
                $status = [
                    'outcome' => 'unknown',
                    'message' => self::RECONCILIATION_DETAILS_INCOMPLETE_MESSAGE,
                ];
            } else {
                $accountInfo = $this->accountInfo->find($payment->service_type, $accountNumber);
                $currentProductId = $accountInfo['productID'] ?? null;
                $currentProductPrice = $accountInfo['productPrice'] ?? null;
                $currentProduct = $this->normalizeProductDetails($currentProductId, $currentProductPrice);

                if (
                    $accountInfo === null ||
                    $currentProduct === null ||
                    $currentProduct['product_id'] !== $payment->product_id ||
                    $currentProduct['product_price'] !== (float) $payment->product_price
                ) {
                    $status = [
                        'outcome' => 'unknown',
                        'message' => self::RECONCILIATION_DETAILS_CHANGED_MESSAGE,
                    ];
                } else {
                    $status = $this->submitRecharge($payment);
                }
            }
        } catch (ConnectionException | RuntimeException $exception) {
            $status = [
                'outcome' => 'unknown',
                'message' => self::RECONCILIATION_STATUS_UNAVAILABLE_MESSAGE,
                'exception' => $exception->getMessage(),
            ];
        }

        if ($status['outcome'] === 'success') {
            $this->markPaymentSuccessful($transaction, $accountNumber, $status);

            return;
        }

        if ($status['outcome'] === 'rejected') {
            $wallet = Wallet::query()->find($transaction->wallet_id);
            $user = $wallet?->user;

            if (!$wallet || !$user) {
                Log::error('Unable to refund rejected FTTH bill payment during reconciliation.', [
                    'ledger_transaction_id' => $transaction->id,
                    'transaction_no' => $transaction->transaction_no,
                    'wallet_id' => $transaction->wallet_id,
                    'wallet_exists' => $wallet !== null,
                    'user_exists' => $user !== null,
                    'account_number' => $accountNumber,
                    'billing_response' => $status,
                ]);

                $this->markPaymentForReview(
                    $transaction,
                    $accountNumber,
                    [
                        ...$status,
                        'message' => 'The payment was rejected, but its wallet owner could not be resolved for refund.',
                        'manual_review_reason' => 'refund_owner_unavailable',
                    ],
                );

                return;
            }

            $this->refundWalletBalance($transaction, $wallet, $user, $accountNumber, $status);

            return;
        }

        if ($transaction->created_at?->lte(now()->subSeconds($maxAgeSeconds))) {
            $this->markPaymentForReview($transaction, $accountNumber, $status);

            return;
        }

        $retrySeconds = (int) config('services.billing.processing_retry_seconds', 300);

        ReconcileStuckFtthBillPaymentsJob::dispatch($transaction->id)->delay(now()->addSeconds(max(1, $retrySeconds)));
    }

    /**
     * @return array{product_id: string, product_price: float}|null
     */
    protected function normalizeProductDetails(mixed $productId, mixed $productPrice): ?array
    {
        if (
            (!is_string($productId) && !is_int($productId)) ||
            trim((string) $productId) === '' ||
            !is_numeric($productPrice) ||
            (float) $productPrice < 1 ||
            round((float) $productPrice, 2) !== (float) $productPrice
        ) {
            return null;
        }

        return [
            'product_id' => (string) $productId,
            'product_price' => round((float) $productPrice, 2),
        ];
    }

    protected function debitWallet(
        Wallet $wallet,
        User $user,
        float $amount,
        string $serviceType,
        string $productId,
        float $productPrice,
        array $snapshot,
        string $orderNo,
        ?string $remark,
        string $idempotencyKey,
        string $accountNumber,
        ?string $ipAddress,
        ?string $userAgent,
    ): array {
        return DB::transaction(function () use (
            $wallet,
            $user,
            $amount,
            $serviceType,
            $productId,
            $productPrice,
            $snapshot,
            $orderNo,
            $remark,
            $idempotencyKey,
            $accountNumber,
            $ipAddress,
            $userAgent,
        ) {
            /** @var Wallet $lockedWallet */
            $lockedWallet = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            $processing = LedgerTransaction::query()
                ->with('billPayment')
                ->where('wallet_id', $lockedWallet->id)
                ->where('type', LedgerTransactionType::FtthBill)
                ->whereIn('status', [LedgerTransactionStatus::Processing, LedgerTransactionStatus::Review])
                ->whereHas('billPayment', function ($query) use ($accountNumber, $serviceType): void {
                    $query
                        ->where('broadband_account_number', $accountNumber)
                        ->where('service_type', $serviceType)
                        ->whereIn('status', [BillPaymentStatus::Processing, BillPaymentStatus::Review]);
                })
                ->first();

            if ($processing) {
                return [
                    'transaction' => $processing,
                    'created' => false,
                ];
            }

            $transaction = $this->ledger->debitWallet(
                wallet: $lockedWallet,
                amount: $amount,
                contraAccount: LedgerAccountCode::FtthClearing,
                type: LedgerTransactionType::FtthBill,
                status: LedgerTransactionStatus::Processing,
                idempotencyKey: $idempotencyKey,
                actorType: WalletActorType::User,
                actorId: $user->id,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
                note: 'FTTH bill ' . $accountNumber,
            );

            BillPayment::query()->create([
                'ledger_transaction_id' => $transaction->id,
                'broadband_account_number' => $accountNumber,
                'order_no' => $orderNo,
                'service_type' => $serviceType,
                'product_id' => $productId,
                'product_price' => $productPrice,
                'snapshot' => $snapshot,
                'remark' => $remark,
                'status' => BillPaymentStatus::Processing,
                'external_response' => [
                    'broadband_account_number' => $accountNumber,
                    'phase' => 'debited',
                ],
            ]);

            return [
                'transaction' => $transaction->refresh()->load('billPayment'),
                'created' => true,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $billingResponse
     * @return array{http_status: int, body: array<string, mixed>}
     */
    protected function completePayment(
        LedgerTransaction $transaction,
        string $accountNumber,
        array $billingResponse,
    ): array {
        $completion = $this->markPaymentSuccessful($transaction, $accountNumber, $billingResponse);
        $completedTransaction = $completion['transaction'];

        if ($completedTransaction->status !== LedgerTransactionStatus::Completed) {
            return $this->existingResponse(
                $completedTransaction->load('billPayment'),
                $accountNumber,
                $completedTransaction->billPayment?->service_type,
            );
        }

        return $this->coded(200, MessageCode::FtthPaid, [
            'data' => [
                'amount' => $completedTransaction->amount,
                'billing_status' => LedgerTransactionStatus::Completed->value,
                'transaction_no' => $completedTransaction->transaction_no,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $billingResponse
     * @return array{http_status: int, body: array<string, mixed>}
     */
    protected function failAndRefund(
        LedgerTransaction $transaction,
        Wallet $wallet,
        User $user,
        string $accountNumber,
        array $billingResponse,
    ): array {
        $refundTransaction = $this->refundWalletBalance($transaction, $wallet, $user, $accountNumber, $billingResponse);

        return $this->coded(502, MessageCode::FtthRefunded, [
            'data' => [
                'amount' => $transaction->amount,
                'billing_status' => LedgerTransactionStatus::Failed->value,
                'transaction_no' => $transaction->transaction_no,
                'refund_transaction_no' => $refundTransaction->transaction_no,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $billingResponse
     * @return array{http_status: int, body: array<string, mixed>}
     */
    protected function leaveProcessing(
        LedgerTransaction $transaction,
        string $accountNumber,
        array $billingResponse,
    ): array {
        BillPayment::query()->updateOrCreate(
            ['ledger_transaction_id' => $transaction->id],
            [
                'broadband_account_number' => $accountNumber,
                'status' => BillPaymentStatus::Processing,
                ...$this->paymentResponseAttributes($billingResponse, $accountNumber, 'awaiting_billing_confirmation'),
            ],
        );

        $delaySeconds = (int) config('services.billing.processing_grace_seconds', 120);

        ReconcileStuckFtthBillPaymentsJob::dispatch($transaction->id)->delay(now()->addSeconds(max(1, $delaySeconds)));

        $this->notifyUser(
            $transaction->wallet?->user,
            BillPaymentNotificationEvent::Processing,
            $transaction,
            $accountNumber,
        );

        return $this->coded(202, MessageCode::FtthProcessing, [
            'success' => true,
            'data' => [
                'amount' => $transaction->amount,
                'billing_status' => LedgerTransactionStatus::Processing->value,
                'transaction_no' => $transaction->transaction_no,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function submitRecharge(BillPayment $payment): array
    {
        $payload = [
            'OrderNo' => (string) $payment->order_no,
            'Account' => (string) $payment->broadband_account_number,
            'ServiceType' => (string) $payment->service_type,
            'ProductID' => (string) $payment->product_id,
            'ProductPrice' => (float) $payment->product_price,
            'Remark' => $payment->remark ?? '',
        ];

        try {
            $response = $this->cgNet->post('RechargeAPI', $payload);
        } catch (ConnectionException | CgApiTokenException $exception) {
            return [
                'outcome' => 'unknown',
                'message' => self::RECHARGE_NO_RESPONSE_MESSAGE,
                'exception' => $exception->getMessage(),
            ];
        } catch (\Throwable $exception) {
            Log::error('Unexpected FTTH recharge submission failure.', [
                'bill_payment_id' => $payment->id,
                'ledger_transaction_id' => $payment->ledger_transaction_id,
                'order_no' => $payment->order_no,
                'exception' => $exception,
            ]);

            return [
                'outcome' => 'unknown',
                'message' => self::RECHARGE_UNKNOWN_MESSAGE,
                'exception' => $exception->getMessage(),
            ];
        }

        $payload = $response->json();
        if ($response->serverError() || in_array($response->status(), [408, 429], true)) {
            return [
                'outcome' => 'unknown',
                'message' => $this->providerMessage($payload, self::RECHARGE_UNKNOWN_MESSAGE),
                'http_status' => $response->status(),
                'payload' => $payload,
            ];
        }

        if (!$response->successful()) {
            return [
                'outcome' => 'rejected',
                'message' => $this->providerMessage($payload, self::RECHARGE_REJECTED_MESSAGE),
                'http_status' => $response->status(),
                'payload' => $payload,
            ];
        }

        if (!is_array($payload) || !is_numeric($payload['ErrCode'] ?? null)) {
            return [
                'outcome' => 'unknown',
                'message' => self::RECHARGE_INVALID_RESPONSE_MESSAGE,
                'http_status' => $response->status(),
                'payload' => $payload,
            ];
        }

        return match ((int) $payload['ErrCode']) {
            0, 1 => [
                'outcome' => 'success',
                'payload' => $payload,
            ],
            2, 100, 500 => [
                'outcome' => 'rejected',
                'message' => $this->providerMessage($payload, self::RECHARGE_REJECTED_MESSAGE),
                'http_status' => $response->status(),
                'payload' => $payload,
            ],
            default => [
                'outcome' => 'unknown',
                'message' => $this->providerMessage($payload, self::RECHARGE_UNKNOWN_MESSAGE),
                'http_status' => $response->status(),
                'payload' => $payload,
            ],
        };
    }

    protected function providerMessage(mixed $payload, string $fallback): string
    {
        return is_array($payload) && is_string($payload['Message'] ?? null) ? $payload['Message'] : $fallback;
    }

    /**
     * Move an unresolved external payment to manual review without refunding it.
     *
     * @param  array<string, mixed>  $billingResponse
     */
    protected function markPaymentForReview(
        LedgerTransaction $transaction,
        string $accountNumber,
        array $billingResponse,
    ): void {
        $paymentId = DB::transaction(function () use ($transaction, $accountNumber, $billingResponse): ?int {
            /** @var LedgerTransaction $locked */
            $locked = LedgerTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== LedgerTransactionStatus::Processing) {
                return null;
            }

            $billPayment = $locked->billPayment()->first();
            $externalResponse = is_array($billPayment?->external_response) ? $billPayment->external_response : [];

            if (($externalResponse['phase'] ?? null) === 'manual_review') {
                return null;
            }

            $reviewResponse = [
                ...$billingResponse,
                'broadband_account_number' => $accountNumber,
                'phase' => 'manual_review',
                'message' => $billingResponse['message'] ?? self::PAYMENT_TIMEOUT_MESSAGE,
            ];

            $locked->update(['status' => LedgerTransactionStatus::Review]);

            $billPayment = BillPayment::query()->updateOrCreate(
                ['ledger_transaction_id' => $locked->id],
                [
                    'broadband_account_number' => $accountNumber,
                    'status' => BillPaymentStatus::Review,
                    'external_response' => $reviewResponse,
                ],
            );

            return $billPayment->id;
        });

        if ($paymentId === null) {
            return;
        }

        $notification = AdminNotification::query()->create([
            'type' => 'other',
            'title' => 'FTTH payment requires manual review',
            'body' => sprintf(
                'FTTH payment %s for account %s could not be confirmed automatically.',
                $transaction->transaction_no,
                $accountNumber,
            ),
            'severity' => 'high',
            'reference_type' => 'bill_payment',
            'reference_id' => $paymentId,
            'sent_at' => now(),
        ]);

        $this->adminNotificationDelivery->deliver($notification, 'reports.view');
    }

    /**
     * @param  array<string, mixed>  $billingResponse
     * @return array{transaction: LedgerTransaction, completed: bool}
     */
    public function markPaymentSuccessful(
        LedgerTransaction $transaction,
        string $accountNumber,
        array $billingResponse,
    ): array {
        $completedTransaction = DB::transaction(function () use ($transaction, $accountNumber, $billingResponse) {
            /** @var LedgerTransaction $locked */
            $locked = LedgerTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== LedgerTransactionStatus::Processing) {
                return [
                    'transaction' => $locked,
                    'completed' => false,
                ];
            }

            $locked->update([
                'status' => LedgerTransactionStatus::Completed,
                'posted_at' => $locked->posted_at ?? now(),
            ]);

            BillPayment::query()->updateOrCreate(
                ['ledger_transaction_id' => $locked->id],
                [
                    'broadband_account_number' => $accountNumber,
                    'status' => BillPaymentStatus::Completed,
                    ...$this->paymentResponseAttributes($billingResponse, $accountNumber),
                    'confirmed_at' => now(),
                ],
            );

            return [
                'transaction' => $locked->refresh(),
                'completed' => true,
            ];
        });

        if ($completedTransaction['completed']) {
            $this->notifyUser(
                $transaction->wallet?->user,
                BillPaymentNotificationEvent::Completed,
                $transaction,
                $accountNumber,
            );
        }

        return $completedTransaction;
    }

    public function resolveReviewSuccessfully(LedgerTransaction $transaction, int $adminId): void
    {
        $completed = DB::transaction(function () use ($transaction, $adminId): bool {
            /** @var LedgerTransaction $locked */
            $locked = LedgerTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if (
                $locked->type !== LedgerTransactionType::FtthBill ||
                $locked->status !== LedgerTransactionStatus::Review
            ) {
                return false;
            }

            $billPayment = $locked->billPayment()->first();
            if ($billPayment === null) {
                throw new RuntimeException('The FTTH bill payment record is missing.');
            }

            $externalResponse = is_array($billPayment->external_response) ? $billPayment->external_response : [];
            $externalResponse['phase'] = 'manual_review_completed';
            $externalResponse['resolved_by'] = $adminId;
            $externalResponse['resolved_at'] = now()->toISOString();

            $locked->update([
                'status' => LedgerTransactionStatus::Completed,
                'posted_at' => $locked->posted_at ?? now(),
            ]);
            $billPayment->update([
                'status' => BillPaymentStatus::Completed,
                'external_response' => $externalResponse,
                'confirmed_at' => now(),
            ]);

            return true;
        });

        if ($completed) {
            $accountNumber = (string) $transaction->billPayment?->broadband_account_number;
            $this->notifyUser(
                $transaction->wallet?->user,
                BillPaymentNotificationEvent::Completed,
                $transaction,
                $accountNumber,
            );
        }
    }

    public function refundReviewPayment(LedgerTransaction $transaction, User $user, int $adminId): LedgerTransaction
    {
        $transaction->loadMissing(['billPayment', 'wallet.user']);

        if (
            $transaction->type !== LedgerTransactionType::FtthBill ||
            $transaction->status !== LedgerTransactionStatus::Review ||
            $transaction->billPayment === null
        ) {
            throw new RuntimeException('Only an FTTH payment under review can be refunded.');
        }

        $accountNumber = (string) $transaction->billPayment->broadband_account_number;
        $billingResponse = is_array($transaction->billPayment->external_response)
            ? $transaction->billPayment->external_response
            : [];
        $billingResponse['phase'] = 'manual_review_refunded';
        $billingResponse['resolved_by'] = $adminId;
        $billingResponse['resolved_at'] = now()->toISOString();

        return $this->refundWalletBalance($transaction, $transaction->wallet, $user, $accountNumber, $billingResponse);
    }

    /**
     * @param  array<string, mixed>  $billingResponse
     */
    public function refundWalletBalance(
        LedgerTransaction $debitTransaction,
        Wallet $wallet,
        User $user,
        string $accountNumber,
        array $billingResponse,
    ): LedgerTransaction {
        $refunded = false;

        $refundTransaction = DB::transaction(function () use (
            $debitTransaction,
            $wallet,
            $user,
            $accountNumber,
            $billingResponse,
            &$refunded,
        ) {
            /** @var LedgerTransaction $lockedDebit */
            $lockedDebit = LedgerTransaction::query()->whereKey($debitTransaction->id)->lockForUpdate()->firstOrFail();

            $existingRefund = LedgerTransaction::query()
                ->where('reversal_of', $lockedDebit->id)
                ->where('type', LedgerTransactionType::Refund)
                ->lockForUpdate()
                ->first();

            if ($existingRefund) {
                return $existingRefund;
            }

            if ($lockedDebit->status === LedgerTransactionStatus::Completed) {
                throw new RuntimeException('Cannot refund a completed FTTH bill payment.');
            }

            if ($lockedDebit->status === LedgerTransactionStatus::Failed) {
                return LedgerTransaction::query()
                    ->where('reversal_of', $lockedDebit->id)
                    ->where('type', LedgerTransactionType::Refund)
                    ->firstOrFail();
            }

            $amount = (float) $lockedDebit->amount;

            $refundTransaction = $this->ledger->creditWallet(
                wallet: $wallet,
                amount: $amount,
                contraAccount: LedgerAccountCode::FtthClearing,
                type: LedgerTransactionType::Refund,
                status: LedgerTransactionStatus::Completed,
                idempotencyKey: 'refund:' . $lockedDebit->idempotency_key,
                actorType: WalletActorType::System,
                actorId: $user->id,
                reversalOf: $lockedDebit->id,
            );

            $lockedDebit->update(['status' => LedgerTransactionStatus::Failed]);

            BillPayment::query()->updateOrCreate(
                ['ledger_transaction_id' => $lockedDebit->id],
                [
                    'broadband_account_number' => $accountNumber,
                    'status' => BillPaymentStatus::Failed,
                    ...$this->paymentResponseAttributes($billingResponse, $accountNumber),
                    'confirmed_at' => now(),
                ],
            );

            $refunded = true;

            return $refundTransaction->refresh();
        });

        if ($refunded) {
            $this->notifyUser(
                $user,
                BillPaymentNotificationEvent::Refunded,
                $debitTransaction,
                $accountNumber,
                $refundTransaction->transaction_no,
            );
        }

        return $refundTransaction;
    }

    protected function notifyUser(
        ?User $user,
        BillPaymentNotificationEvent $event,
        LedgerTransaction $transaction,
        string $accountNumber,
        ?string $refundTransactionNo = null,
    ): void {
        $user?->notify(
            new FtthBillPaymentStatusNotification(
                event: $event,
                transactionNo: $transaction->transaction_no,
                amount: (float) $transaction->amount,
                accountNumber: $accountNumber,
                refundTransactionNo: $refundTransactionNo,
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    protected function productSnapshot(array $product): array
    {
        return [
            'product_id' => $product['productID'] ?? null,
            'product_name' => $product['productName'] ?? null,
            'product_price' => $product['productPrice'] ?? null,
            'month_cycle' => $product['monthCycle'] ?? null,
            'month_cycle_unit' => $product['monthCycleUnit'] ?? null,
            'product_line' => $product['productLine'] ?? null,
            'product_speed' => $product['productSpeed'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $billingResponse
     * @return array{external_response: array<string, mixed>}
     */
    protected function paymentResponseAttributes(
        array $billingResponse,
        string $accountNumber,
        ?string $phase = null,
    ): array {
        $externalResponse = [...$billingResponse, 'broadband_account_number' => $accountNumber];

        if ($phase !== null) {
            $externalResponse['phase'] = $phase;
        }

        return [
            'external_response' => $externalResponse,
        ];
    }

    /**
     * @return array{http_status: int, body: array<string, mixed>}
     */
    protected function existingResponse(
        LedgerTransaction $existing,
        ?string $accountNumber = null,
        ?string $serviceType = null,
    ): array {
        if (
            ($accountNumber !== null && !$this->replayMatchesAccount($existing, $accountNumber)) ||
            ($serviceType !== null &&
                $existing->billPayment?->service_type !== null &&
                $existing->billPayment->service_type !== $serviceType)
        ) {
            return $this->coded(409, MessageCode::FtthCannotReplay);
        }

        $status = $existing->status;

        if ($existing->type === LedgerTransactionType::FtthBill && $status === LedgerTransactionStatus::Completed) {
            return $this->coded(200, MessageCode::FtthAlreadyProcessed, [
                'success' => true,
                'data' => [
                    'amount' => $existing->amount,
                    'billing_status' => LedgerTransactionStatus::Completed->value,
                    'transaction_no' => $existing->transaction_no,
                ],
            ]);
        }

        if ($existing->type === LedgerTransactionType::FtthBill && $status === LedgerTransactionStatus::Failed) {
            $refund = LedgerTransaction::query()
                ->where('reversal_of', $existing->id)
                ->where('type', LedgerTransactionType::Refund)
                ->first();

            return $this->coded(502, MessageCode::FtthRefunded, [
                'data' => [
                    'amount' => $existing->amount,
                    'billing_status' => LedgerTransactionStatus::Failed->value,
                    'transaction_no' => $existing->transaction_no,
                    'refund_transaction_no' => $refund?->transaction_no,
                ],
            ]);
        }

        if ($existing->type === LedgerTransactionType::FtthBill && $status === LedgerTransactionStatus::Processing) {
            return $this->coded(202, MessageCode::FtthAlreadyProcessing, [
                'success' => true,
                'data' => [
                    'amount' => $existing->amount,
                    'billing_status' => LedgerTransactionStatus::Processing->value,
                    'transaction_no' => $existing->transaction_no,
                ],
            ]);
        }

        if ($existing->type === LedgerTransactionType::FtthBill && $status === LedgerTransactionStatus::Review) {
            return $this->coded(202, MessageCode::FtthProcessing, [
                'success' => true,
                'data' => [
                    'amount' => $existing->amount,
                    'billing_status' => LedgerTransactionStatus::Review->value,
                    'transaction_no' => $existing->transaction_no,
                ],
            ]);
        }

        if ($existing->type === LedgerTransactionType::Refund) {
            return $this->coded(502, MessageCode::FtthRefunded, [
                'data' => [
                    'amount' => $existing->amount,
                    'billing_status' => LedgerTransactionStatus::Failed->value,
                    'transaction_no' => $existing->transaction_no,
                ],
            ]);
        }

        return $this->coded(409, MessageCode::FtthCannotReplay, [
            'data' => [
                'amount' => $existing->amount,
                'transaction_no' => $existing->transaction_no,
            ],
        ]);
    }

    protected function replayMatchesAccount(LedgerTransaction $existing, string $accountNumber): bool
    {
        $stored = $existing->billPayment?->broadband_account_number;

        if (!is_string($stored) || trim($stored) === '') {
            return true;
        }

        return trim($stored) === trim($accountNumber);
    }

    /**
     * @return array{http_status: int, body: array<string, mixed>}
     */
    protected function accountMissing(): array
    {
        return $this->coded(422, MessageCode::FtthAccountMissing, [
            'errors' => [
                'broadband_account_number' => [MessageCode::FtthAccountMissing->message()],
            ],
        ]);
    }

    protected function coded(int $httpStatus, MessageCode $code, array $body = []): array
    {
        $success = $body['success'] ?? $httpStatus >= 200 && $httpStatus < 300;

        return $this->response($httpStatus, ApiMessage::attach($code, array_merge(['success' => $success], $body)));
    }

    protected function response(int $httpStatus, array $body): array
    {
        return [
            'http_status' => $httpStatus,
            'body' => $body,
        ];
    }
}
