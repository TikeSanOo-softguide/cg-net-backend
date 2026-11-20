<?php

namespace Tests\Feature;

use App\Enums\PackageOrderStatus;
use App\Exports\PackageOrderExport;
use App\Models\LedgerTransaction;
use App\Models\PackageOrder;
use App\Models\User;
use Tests\TestCase;

class PackageOrderExportTest extends TestCase
{
    public function test_package_order_export_maps_order_details_under_matching_headings(): void
    {
        $user = new User();
        $user->forceFill([
            'name' => 'A Customer',
            'phone' => '09123456789',
        ]);

        $transaction = new LedgerTransaction();
        $transaction->forceFill([
            'transaction_no' => 'TX-100',
            'amount' => '2500.00',
        ]);

        $order = new PackageOrder();
        $order->forceFill([
            'id' => 7,
            'order_no' => 'ORDER-100',
            'status' => PackageOrderStatus::Completed,
            'created_at' => '2026-11-18 10:11:12',
            'snapshot' => [
                'product_name' => 'Home 100M',
                'product_id' => 'PRODUCT-100',
                'product_line' => 'FTTH',
                'product_speed' => '100 Mbps',
                'product_price' => '2300.00',
                'term_cycle' => 12,
                'term_cycle_unit' => 'Months',
                'installation_fee' => '200.00',
                'price' => '2500.00',
            ],
        ]);
        $order->setRelation('user', $user);
        $order->setRelation('ledgerTransaction', $transaction);

        $export = new PackageOrderExport(PackageOrder::query());
        $headings = $export->headings();
        $row = $export->map($order);

        $this->assertSame(count($headings), count($row));
        $this->assertSame('Transaction No.', $headings[0]);
        $this->assertSame('TX-100', $row[0]);
        $this->assertSame('ORDER-100', $row[1]);
        $this->assertSame('A Customer', $row[2]);
        $this->assertSame('Product ID', $headings[4]);
        $this->assertSame('PRODUCT-100', $row[4]);
        $this->assertSame('Product Name', $headings[5]);
        $this->assertSame('Home 100M', $row[5]);
        $this->assertSame('FTTH', $row[6]);
        $this->assertSame('100 Mbps', $row[7]);
        $this->assertSame('2300.00', $row[8]);
        $this->assertSame(12, $row[9]);
        $this->assertSame('Months', $row[10]);
        $this->assertSame('200.00', $row[11]);
        $this->assertSame('2500.00', $row[12]);
        $this->assertSame(PackageOrderStatus::Completed->value, $row[13]);
        $this->assertSame('2026-11-18 10:11:12', $row[16]);
    }
}
