<?php

namespace Tests\Feature;

use App\Exports\BillPaymentExport;
use App\Models\BillPayment;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Models\Wallet;
use Tests\TestCase;

class BillPaymentExportTest extends TestCase
{
    public function test_order_number_broadband_account_and_service_type_align_with_their_headers(): void
    {
        $customer = new User();
        $customer->forceFill([
            'name' => 'A Customer',
            'phone' => '09123456789',
        ]);

        $wallet = new Wallet();
        $wallet->setRelation('user', $customer);

        $transaction = new LedgerTransaction();
        $transaction->forceFill([
            'transaction_no' => 'TX-100',
            'amount' => '2500.00',
        ]);
        $transaction->setRelation('wallet', $wallet);

        $payment = new BillPayment();
        $payment->forceFill([
            'order_no' => 'ORDER-100',
            'broadband_account_number' => 'BB-123',
            'service_type' => 'CG',
            'snapshot' => [],
        ]);
        $payment->setRelation('ledgerTransaction', $transaction);

        $export = new BillPaymentExport(BillPayment::query());
        $headings = $export->headings();
        $row = $export->map($payment);

        $this->assertSame(count($headings), count($row));
        $this->assertSame('Order No.', $headings[1]);
        $this->assertSame('ORDER-100', $row[1]);
        $this->assertSame('Service Type', $headings[4]);
        $this->assertSame('CG', $row[4]);
        $this->assertSame('Broadband Account', $headings[5]);
        $this->assertSame('BB-123', $row[5]);
    }
}
