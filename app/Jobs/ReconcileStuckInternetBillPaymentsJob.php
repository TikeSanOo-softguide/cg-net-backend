<?php

namespace App\Jobs;

use App\Models\LedgerTransaction;
use App\Services\InternetBill\InternetBillPaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ReconcileStuckInternetBillPaymentsJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 120;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $ledgerTransactionId,
    ) {
        $this->onQueue('billing-reconciliation');
    }

    public function uniqueId(): string
    {
        return (string) $this->ledgerTransactionId;
    }

    public function handle(InternetBillPaymentService $payments): void
    {
        $transaction = LedgerTransaction::query()->find($this->ledgerTransactionId);

        if ($transaction === null) {
            return;
        }

        try {
            $payments->reconcile($transaction);
        } catch (Throwable $throwable) {
            $payments->handleReconciliationFailure($transaction, $throwable);
        }
    }
}
