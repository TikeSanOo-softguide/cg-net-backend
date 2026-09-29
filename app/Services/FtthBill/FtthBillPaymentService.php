<?php

namespace App\Services\FtthBill;

use App\Enums\BillPaymentNotificationEvent;
use App\Enums\BillPaymentStatus;
use App\Enums\WalletActorType;
use App\Enums\WalletEntryType;
use App\Enums\WalletStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\BillPayment;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletEntry;
use App\Models\WalletTransaction;
use App\Jobs\ReconcileStuckFtthBillPaymentsJob;
use App\Notifications\FtthBillPaymentStatusNotification;
use App\Services\Billing\BillingServerClient;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class FtthBillPaymentService
{
    public function __construct(protected BillingServerClient $billing) {}

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

        $existing = WalletTransaction::query()
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
            $existing = WalletTransaction::query()
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
     * Settle a stuck Processing FTTH debit after consulting the billing server.
     */
    public function reconcile(WalletTransaction $transaction): void
    {
        if ($transaction->type !== WalletTransactionType::FtthBill) {
            return;
        }

        $transaction->refresh()->loadMissing(['billPayment', 'wallet.user']);

        if ($transaction->status !== WalletTransactionStatus::Processing) {
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

        // Still unknown: refund after max age, otherwise retry later via the queue.
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

        ReconcileStuckFtthBillPaymentsJob::dispatch($transaction->id)
            ->delay(now()->addSeconds(max(1, $retrySeconds)));
    }

    protected function debitWallet(
        Wallet $wallet,
        User $user,
        int $amount,
        string $idempotencyKey,
        string $accountNumber,
        ?string $ipAddress,
        ?string $userAgent,
    ): WalletTransaction {
        return DB::transaction(function () use (
            $wallet,
            $user,
            $amount,
            $idempotencyKey,
            $accountNumber,
            $ipAddress,
            $userAgent,
        ) {
            /** @var Wallet $locked */
            $locked = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== WalletStatus::Active) {
                throw ValidationException::withMessages([
                    'wallet' => ['Wallet is not active.'],
                ]);
            }

            if ($locked->balance < $amount) {
                throw ValidationException::withMessages([
                    'amount' => ['Insufficient wallet balance.'],
                ]);
            }

            $beforeBalance = $locked->balance;
            $locked->balance -= $amount;
            $locked->incrementVersion();
            $locked->save();
            $afterBalance = $locked->balance;

            $transaction = WalletTransaction::query()->create([
                'wallet_id' => $locked->id,
                'transaction_no' => 'FTTH-' . Str::ulid(),
                'type' => WalletTransactionType::FtthBill,
                'status' => WalletTransactionStatus::Processing,
                'amount' => $amount,
                'idempotency_key' => $idempotencyKey,
                'actor_type' => WalletActorType::User,
                'actor_id' => $user->id,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            WalletEntry::query()->create([
                'wallet_transaction_id' => $transaction->id,
                'wallet_id' => $locked->id,
                'amount' => $amount,
                'balance_before' => $beforeBalance,
                'balance_after' => $afterBalance,
                'type' => WalletEntryType::Debit,
            ]);

            BillPayment::query()->create([
                'wallet_transaction_id' => $transaction->id,
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
        WalletTransaction $transaction,
        string $accountNumber,
        array $billingResponse,
    ): array {
        $this->markPaymentSuccessful($transaction, $accountNumber, $billingResponse);

        return $this->response(200, [
            'success' => true,
            'message' => 'FTTH bill paid successfully.',
            'data' => [
                'amount' => $transaction->amount,
                'billing_status' => WalletTransactionStatus::Completed->value,
                'transaction_no' => $transaction->transaction_no,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $billingResponse
     * @return array{http_status: int, body: array<string, mixed>}
     */
    protected function failAndRefund(
        WalletTransaction $transaction,
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
                'billing_status' => WalletTransactionStatus::Failed->value,
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
        WalletTransaction $transaction,
        string $accountNumber,
        array $billingResponse,
    ): array {
        BillPayment::query()->updateOrCreate(
            ['wallet_transaction_id' => $transaction->id],
            [
                'broadband_account_number' => $accountNumber,
                'status' => BillPaymentStatus::Processing,
                ...$this->externalBillAttributes($billingResponse, $accountNumber, 'awaiting_billing_confirmation'),
            ],
        );

        $delaySeconds = (int) config('services.billing.processing_grace_seconds', 120);

        ReconcileStuckFtthBillPaymentsJob::dispatch($transaction->id)
            ->delay(now()->addSeconds(max(1, $delaySeconds)));

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
                'billing_status' => WalletTransactionStatus::Processing->value,
                'transaction_no' => $transaction->transaction_no,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $billingResponse
     */
    public function markPaymentSuccessful(
        WalletTransaction $transaction,
        string $accountNumber,
        array $billingResponse,
    ): void {
        $completed = DB::transaction(function () use ($transaction, $accountNumber, $billingResponse) {
            /** @var WalletTransaction $locked */
            $locked = WalletTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== WalletTransactionStatus::Processing) {
                return false;
            }

            $locked->update(['status' => WalletTransactionStatus::Completed]);

            BillPayment::query()->updateOrCreate(
                ['wallet_transaction_id' => $locked->id],
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
        WalletTransaction $debitTransaction,
        Wallet $wallet,
        User $user,
        string $accountNumber,
        array $billingResponse,
    ): WalletTransaction {
        $refunded = false;

        $refundTransaction = DB::transaction(function () use ($debitTransaction, $wallet, $user, $accountNumber, $billingResponse, &$refunded) {
            /** @var WalletTransaction $lockedDebit */
            $lockedDebit = WalletTransaction::query()->whereKey($debitTransaction->id)->lockForUpdate()->firstOrFail();

            $existingRefund = WalletTransaction::query()
                ->where('reversal_of', $lockedDebit->id)
                ->where('type', WalletTransactionType::Refund)
                ->lockForUpdate()
                ->first();

            if ($existingRefund) {
                return $existingRefund;
            }

            if ($lockedDebit->status === WalletTransactionStatus::Completed) {
                throw new RuntimeException('Cannot refund a completed FTTH bill payment.');
            }

            if ($lockedDebit->status === WalletTransactionStatus::Failed) {
                $existingRefund = WalletTransaction::query()
                    ->where('reversal_of', $lockedDebit->id)
                    ->where('type', WalletTransactionType::Refund)
                    ->firstOrFail();

                return $existingRefund;
            }

            /** @var Wallet $lockedWallet */
            $lockedWallet = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            $amount = (int) $lockedDebit->amount;
            $beforeBalance = $lockedWallet->balance;
            $lockedWallet->balance += $amount;
            $lockedWallet->incrementVersion();
            $lockedWallet->save();
            $afterBalance = $lockedWallet->balance;

            $refundTransaction = WalletTransaction::query()->create([
                'wallet_id' => $lockedWallet->id,
                'transaction_no' => 'REFUND-' . Str::ulid(),
                'type' => WalletTransactionType::Refund,
                'status' => WalletTransactionStatus::Completed,
                'amount' => $amount,
                'idempotency_key' => 'refund:' . $lockedDebit->idempotency_key,
                'reversal_of' => $lockedDebit->id,
                'actor_type' => WalletActorType::System,
                'actor_id' => $user->id,
            ]);

            WalletEntry::query()->create([
                'wallet_transaction_id' => $refundTransaction->id,
                'wallet_id' => $lockedWallet->id,
                'amount' => $amount,
                'balance_before' => $beforeBalance,
                'balance_after' => $afterBalance,
                'type' => WalletEntryType::Credit,
            ]);

            $lockedDebit->update(['status' => WalletTransactionStatus::Failed]);

            BillPayment::query()->updateOrCreate(
                ['wallet_transaction_id' => $lockedDebit->id],
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
        WalletTransaction $transaction,
        string $accountNumber,
        ?string $refundTransactionNo = null,
    ): void {
        $user?->notify(new FtthBillPaymentStatusNotification(
            event: $event,
            transactionNo: $transaction->transaction_no,
            amount: (int) $transaction->amount,
            accountNumber: $accountNumber,
            refundTransactionNo: $refundTransactionNo,
        ));
    }

    /**
     * Map billing-server fields onto bill_payments.external_* columns after extend/reconcile.
     *
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
            $billingResponse['billing_ref']
            ?? $billingResponse['external_bill_ref']
            ?? $payload['billing_ref']
            ?? $payload['external_bill_ref']
            ?? $payload['reference']
            ?? null;

        $paymentRef =
            $billingResponse['external_payment_ref']
            ?? $billingResponse['payment_ref']
            ?? $payload['payment_ref']
            ?? $payload['external_payment_ref']
            ?? $payload['transaction_id']
            ?? null;

        $externalResponse = [
            ...$billingResponse,
            'broadband_account_number' => $accountNumber,
        ];

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
    protected function existingResponse(WalletTransaction $existing): array
    {
        $status = $existing->status;

        if ($existing->type === WalletTransactionType::FtthBill && $status === WalletTransactionStatus::Completed) {
            return $this->response(200, [
                'success' => true,
                'message' => 'FTTH bill payment already processed.',
                'data' => [
                    'amount' => $existing->amount,
                    'billing_status' => WalletTransactionStatus::Completed->value,
                    'transaction_no' => $existing->transaction_no,
                ],
            ]);
        }

        if ($existing->type === WalletTransactionType::FtthBill && $status === WalletTransactionStatus::Failed) {
            $refund = WalletTransaction::query()
                ->where('reversal_of', $existing->id)
                ->where('type', WalletTransactionType::Refund)
                ->first();

            return $this->response(502, [
                'success' => false,
                'message' => 'External billing payment failed. Wallet was refunded.',
                'data' => [
                    'amount' => $existing->amount,
                    'billing_status' => WalletTransactionStatus::Failed->value,
                    'transaction_no' => $existing->transaction_no,
                    'refund_transaction_no' => $refund?->transaction_no,
                ],
            ]);
        }

        if ($existing->type === WalletTransactionType::FtthBill && $status === WalletTransactionStatus::Processing) {
            return $this->response(202, [
                'success' => true,
                'message' => 'This payment request is already being processed.',
                'data' => [
                    'amount' => $existing->amount,
                    'billing_status' => WalletTransactionStatus::Processing->value,
                    'transaction_no' => $existing->transaction_no,
                ],
            ]);
        }

        if ($existing->type === WalletTransactionType::Refund) {
            return $this->response(502, [
                'success' => false,
                'message' => 'External billing payment failed. Wallet was refunded.',
                'data' => [
                    'amount' => $existing->amount,
                    'billing_status' => WalletTransactionStatus::Failed->value,
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
