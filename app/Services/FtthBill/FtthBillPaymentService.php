<?php

namespace App\Services\FtthBill;

use App\Enums\BillPaymentNotificationEvent;
use App\Enums\BillPaymentStatus;
use App\Enums\LedgerAccountCode;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\WalletActorType;
use App\Enums\WalletStatus;
use App\Jobs\ReconcileStuckFtthBillPaymentsJob;
use App\Models\BillPayment;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Notifications\FtthBillPaymentStatusNotification;
use App\Services\Billing\BillingServerClient;
use App\Services\BroarbandAccount\BroadbandAccountService;
use App\Services\Ledger\LedgerPoster;
use Illuminate\Support\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class FtthBillPaymentService
{
    public function __construct(
        protected BillingServerClient $billing,
        protected LedgerPoster $ledger,
        protected BroadbandAccountService $broadbandAccounts,
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
        string $idempotencyKey,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): array {
        $wallet = $user->wallet;

        if (!$wallet) {
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

        $existing = LedgerTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $this->existingResponse($existing);
        }

        try {
            $amount = $this->billing->lookupBillAmount($accountNumber);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'broadband_account_number' => [$exception->getMessage()],
            ]);
        }

        if ($wallet->balance < $amount) {
            return $this->response(422, [
                'success' => false,
                'message' => 'Insufficient wallet balance.',
                'errors' => [
                    'amount' => ['Insufficient wallet balance.'],
                ],
            ]);
        }

        try {
            $pendingTransaction = $this->debitWallet(
                wallet: $wallet,
                user: $user,
                amount: $amount,
                idempotencyKey: $idempotencyKey,
                accountNumber: $accountNumber,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );
        } catch (UniqueConstraintViolationException) {
            $existing = LedgerTransaction::query()
                ->where('wallet_id', $wallet->id)
                ->where('idempotency_key', $idempotencyKey)
                ->firstOrFail();

            return $this->existingResponse($existing);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        $billingResponse = $this->billing->extendPlan(
            $accountNumber,
            (string) $user->name,
            $amount,
            $pendingTransaction->transaction_no,
        );

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
            return $this->response(422, [
                'success' => false,
                'message' => 'Broadband account number not found.',
            ]);
        }

        try {
            $slip = $this->billing->lookupPendingBillSlip($accountNumber);
        } catch (RuntimeException $exception) {
            return $this->response(502, [
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        }

        $billMonth = $slip['bill_month'];
        $billMonthLabel = Carbon::createFromFormat('!Y-m', $billMonth)->format('F Y');
        unset($slip['bill_month'], $slip['bill_month_label'], $slip['paid_slips']);

        $broadbandAccount = $this->broadbandAccounts->findByAccountNumber($accountNumber);

        return $this->response(200, [
            'success' => true,
            'message' => 'Pending FTTH bill slip retrieved.',
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
            return $this->response(422, [
                'success' => false,
                'message' => 'Broadband account number not found.',
            ]);
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
            return $this->response(502, [
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        }

        return $this->response(200, [
            'success' => true,
            'message' => 'Paid FTTH bill slips retrieved.',
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
     * Settle a stuck Processing FTTH debit after consulting the billing server.
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

        $status = $this->billing->lookupPaymentStatus($transaction->transaction_no);
        $accountNumber =
            (string) ($transaction->billPayment?->broadband_account_number ??
                ($transaction->billPayment?->external_response['broadband_account_number'] ??
                    ($transaction->billPayment?->external_response['account_number'] ??
                        ($transaction->wallet?->user?->broadband_account_number ?? ''))));

        if ($status['outcome'] === 'success') {
            $this->markPaymentSuccessful($transaction, $accountNumber, $status);

            return;
        }

        if (in_array($status['outcome'], ['rejected', 'not_found'], true)) {
            $wallet = Wallet::query()->find($transaction->wallet_id);
            $user = $wallet?->user;

            if ($wallet && $user) {
                $this->refundWalletBalance($transaction, $wallet, $user, $accountNumber, $status);
            }

            return;
        }

        if ($transaction->created_at?->lte(now()->subSeconds($maxAgeSeconds))) {
            $wallet = Wallet::query()->find($transaction->wallet_id);
            $user = $wallet?->user;

            if ($wallet && $user) {
                $this->refundWalletBalance($transaction, $wallet, $user, $accountNumber, [
                    ...$status,
                    'message' => $status['message'] ?? 'Payment timed out waiting for billing confirmation.',
                    'reconciled_as' => 'timeout_refund',
                ]);
            }

            return;
        }

        $retrySeconds = (int) config('services.billing.processing_retry_seconds', 300);

        ReconcileStuckFtthBillPaymentsJob::dispatch($transaction->id)->delay(now()->addSeconds(max(1, $retrySeconds)));
    }

    protected function debitWallet(
        Wallet $wallet,
        User $user,
        int $amount,
        string $idempotencyKey,
        string $accountNumber,
        ?string $ipAddress,
        ?string $userAgent,
    ): LedgerTransaction {
        return DB::transaction(function () use (
            $wallet,
            $user,
            $amount,
            $idempotencyKey,
            $accountNumber,
            $ipAddress,
            $userAgent,
        ) {
            $transaction = $this->ledger->debitWallet(
                wallet: $wallet,
                amount: $amount,
                contraAccount: LedgerAccountCode::FtthClearing,
                type: LedgerTransactionType::FtthBill,
                status: LedgerTransactionStatus::Processing,
                idempotencyKey: $idempotencyKey,
                actorType: WalletActorType::User,
                actorId: $user->id,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            );

            BillPayment::query()->create([
                'ledger_transaction_id' => $transaction->id,
                'broadband_account_number' => $accountNumber,
                'status' => BillPaymentStatus::Processing,
                'external_response' => [
                    'broadband_account_number' => $accountNumber,
                    'phase' => 'debited',
                ],
            ]);

            return $transaction->refresh();
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
        $this->markPaymentSuccessful($transaction, $accountNumber, $billingResponse);

        return $this->response(200, [
            'success' => true,
            'message' => 'FTTH bill paid successfully.',
            'data' => [
                'amount' => $transaction->amount,
                'billing_status' => LedgerTransactionStatus::Completed->value,
                'transaction_no' => $transaction->transaction_no,
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

        return $this->response(502, [
            'success' => false,
            'message' => 'External billing payment failed. Wallet was refunded.',
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
                ...$this->externalBillAttributes($billingResponse, $accountNumber, 'awaiting_billing_confirmation'),
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

        return $this->response(202, [
            'success' => true,
            'message' => 'FTTH bill payment is processing. Confirmation is pending from the billing server.',
            'data' => [
                'amount' => $transaction->amount,
                'billing_status' => LedgerTransactionStatus::Processing->value,
                'transaction_no' => $transaction->transaction_no,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $billingResponse
     */
    public function markPaymentSuccessful(
        LedgerTransaction $transaction,
        string $accountNumber,
        array $billingResponse,
    ): void {
        $completed = DB::transaction(function () use ($transaction, $accountNumber, $billingResponse) {
            /** @var LedgerTransaction $locked */
            $locked = LedgerTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== LedgerTransactionStatus::Processing) {
                return false;
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
                    ...$this->externalBillAttributes($billingResponse, $accountNumber),
                    'confirmed_at' => now(),
                ],
            );

            return true;
        });

        if ($completed) {
            $this->notifyUser(
                $transaction->wallet?->user,
                BillPaymentNotificationEvent::Completed,
                $transaction,
                $accountNumber,
            );
        }
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

            $amount = (int) $lockedDebit->amount;

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
                    ...$this->externalBillAttributes($billingResponse, $accountNumber),
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
                amount: (int) $transaction->amount,
                accountNumber: $accountNumber,
                refundTransactionNo: $refundTransactionNo,
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $billingResponse
     * @return array{
     *     external_bill_ref: string|null,
     *     external_payment_ref: string|null,
     *     external_response: array<string, mixed>
     * }
     */
    protected function externalBillAttributes(
        array $billingResponse,
        string $accountNumber,
        ?string $phase = null,
    ): array {
        $payload = is_array($billingResponse['payload'] ?? null) ? $billingResponse['payload'] : [];

        $billRef =
            $billingResponse['billing_ref'] ??
            ($billingResponse['external_bill_ref'] ??
                ($payload['billing_ref'] ?? ($payload['external_bill_ref'] ?? ($payload['reference'] ?? null))));

        $paymentRef =
            $billingResponse['external_payment_ref'] ??
            ($billingResponse['payment_ref'] ??
                ($payload['payment_ref'] ??
                    ($payload['external_payment_ref'] ?? ($payload['transaction_id'] ?? null))));

        $externalResponse = [...$billingResponse, 'broadband_account_number' => $accountNumber];

        if ($phase !== null) {
            $externalResponse['phase'] = $phase;
        }

        return [
            'external_bill_ref' => $billRef !== null ? (string) $billRef : null,
            'external_payment_ref' => $paymentRef !== null ? (string) $paymentRef : null,
            'external_response' => $externalResponse,
        ];
    }

    /**
     * @return array{http_status: int, body: array<string, mixed>}
     */
    protected function existingResponse(LedgerTransaction $existing): array
    {
        $status = $existing->status;

        if ($existing->type === LedgerTransactionType::FtthBill && $status === LedgerTransactionStatus::Completed) {
            return $this->response(200, [
                'success' => true,
                'message' => 'FTTH bill payment already processed.',
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

            return $this->response(502, [
                'success' => false,
                'message' => 'External billing payment failed. Wallet was refunded.',
                'data' => [
                    'amount' => $existing->amount,
                    'billing_status' => LedgerTransactionStatus::Failed->value,
                    'transaction_no' => $existing->transaction_no,
                    'refund_transaction_no' => $refund?->transaction_no,
                ],
            ]);
        }

        if ($existing->type === LedgerTransactionType::FtthBill && $status === LedgerTransactionStatus::Processing) {
            return $this->response(202, [
                'success' => true,
                'message' => 'This payment request is already being processed.',
                'data' => [
                    'amount' => $existing->amount,
                    'billing_status' => LedgerTransactionStatus::Processing->value,
                    'transaction_no' => $existing->transaction_no,
                ],
            ]);
        }

        if ($existing->type === LedgerTransactionType::Refund) {
            return $this->response(502, [
                'success' => false,
                'message' => 'External billing payment failed. Wallet was refunded.',
                'data' => [
                    'amount' => $existing->amount,
                    'billing_status' => LedgerTransactionStatus::Failed->value,
                    'transaction_no' => $existing->transaction_no,
                ],
            ]);
        }

        return $this->response(409, [
            'success' => false,
            'message' => 'This payment request cannot be replayed.',
            'data' => [
                'amount' => $existing->amount,
                'transaction_no' => $existing->transaction_no,
            ],
        ]);
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
