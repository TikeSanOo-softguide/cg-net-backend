<?php

namespace Tests\Feature;

use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Exports\TransactionExport;
use App\Models\LedgerTransaction;
use Carbon\Carbon;
use Tests\TestCase;

class TransactionExportTest extends TestCase
{
    public function test_posted_at_follows_created_at_in_the_export(): void
    {
        $transaction = new LedgerTransaction();
        $transaction->forceFill([
            'transaction_no' => 'TX-100',
            'type' => LedgerTransactionType::Topup,
            'status' => LedgerTransactionStatus::Completed,
            'created_at' => Carbon::parse('2026-11-18 09:00:00'),
            'posted_at' => Carbon::parse('2026-11-18 09:05:00'),
        ]);
        $transaction->setRelation('reversalOf', null);
        $transaction->setRelation('wallet', null);
        $transaction->setRelation('entries', collect());
        $transaction->setRelation('billPayment', null);
        $transaction->setRelation('packageOrder', null);
        $transaction->setRelation('topUpCard', null);

        $export = new TransactionExport(LedgerTransaction::query());
        $headings = $export->headings();
        $row = $export->map($transaction);

        $this->assertSame('Created At', $headings[count($headings) - 2]);
        $this->assertSame('Posted At', $headings[count($headings) - 1]);
        $this->assertSame('2026-11-18 09:00:00', $row[count($row) - 2]);
        $this->assertSame('2026-11-18 09:05:00', $row[count($row) - 1]);
    }
}
