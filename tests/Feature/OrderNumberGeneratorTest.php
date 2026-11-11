<?php

namespace Tests\Feature;

use App\Enums\BillPaymentStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\PackageOrderStatus;
use App\Models\BillPayment;
use App\Models\LedgerTransaction;
use App\Models\PackageOrder;
use App\Models\User;
use App\Services\Order\OrderNumberGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_duplicate_bill_payment_order_numbers_are_rejected(): void
    {
        $first = LedgerTransaction::factory()->create(['type' => LedgerTransactionType::FtthBill]);
        $second = LedgerTransaction::factory()->create(['type' => LedgerTransactionType::FtthBill]);

        BillPayment::query()->create([
            'ledger_transaction_id' => $first->id,
            'order_no' => 'ORD2611090000001',
            'status' => BillPaymentStatus::Processing,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        BillPayment::query()->create([
            'ledger_transaction_id' => $second->id,
            'order_no' => 'ORD2611090000001',
            'status' => BillPaymentStatus::Processing,
        ]);
    }

    public function test_duplicate_package_order_numbers_are_rejected(): void
    {
        $user = User::factory()->create();

        PackageOrder::query()->create([
            'user_id' => $user->id,
            'status' => PackageOrderStatus::Processing,
            'order_no' => 'ORD2611090000001',
            'snapshot' => ['product_id' => 'pkg-1'],
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        PackageOrder::query()->create([
            'user_id' => $user->id,
            'status' => PackageOrderStatus::Processing,
            'order_no' => 'ORD2611090000001',
            'snapshot' => ['product_id' => 'pkg-2'],
        ]);
    }

    public function test_sequential_generation_from_separate_calls_stays_unique(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-09 08:00:00'));
        $generator = app(OrderNumberGenerator::class);
        $ids = [];

        for ($i = 0; $i < 8; $i++) {
            $ids[] = $generator->generate();
        }

        $this->assertSame(array_unique($ids), $ids);
        $this->assertSame('ORD2611090000001', $ids[0]);
        $this->assertSame('ORD2611090000008', $ids[7]);
    }
}
