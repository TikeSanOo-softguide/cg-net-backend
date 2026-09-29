<?php

namespace App\Jobs;

use App\Models\WalletTransaction;
use App\Services\FtthBill\FtthBillPaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReconcileStuckFtthBillPaymentsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 120;

    public function __construct(
        public readonly int $walletTransactionId,
    ) {
        $this->onQueue('billing-reconciliation');
    }

    public function uniqueId(): string
    {
        return (string) $this->walletTransactionId;
    }

    public function handle(FtthBillPaymentService $payments): void
    {
        $transaction = WalletTransaction::query()->find($this->walletTransactionId);

        if ($transaction === null) {
            return;
        }

        try {
            $payments->reconcile($transaction);
        } catch (Throwable $throwable) {
            Log::error('Failed to reconcile FTTH bill payment.', [
                'wallet_transaction_id' => $transaction->id,
                'transaction_no' => $transaction->transaction_no,
                'exception' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }
    }
}
