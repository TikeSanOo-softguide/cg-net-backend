<?php

namespace Tests\Unit;

use App\Enums\TransactionIdPrefix;
use App\Services\Ledger\TransactionIdGenerator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TransactionIdGeneratorTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    #[DataProvider('prefixFormats')]
    public function test_generated_ids_match_prefix_date_and_length(
        TransactionIdPrefix $prefix,
        string $expectedDate,
        int $randomLength,
    ): void {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-09 15:00:00'));

        $generator = new TransactionIdGenerator;
        $id = $generator->generate($prefix);

        $this->assertSame(TransactionIdGenerator::LENGTH, strlen($id));
        $this->assertSame($prefix->value, substr($id, 0, strlen($prefix->value)));
        $this->assertSame($expectedDate, substr($id, strlen($prefix->value), 6));
        $this->assertSame($randomLength, $prefix->randomLength());
        $this->assertTrue($generator->matches($id, $prefix));
        $this->assertSame(strtoupper($id), $id);
        $this->assertMatchesRegularExpression(
            '/^['.preg_quote(TransactionIdGenerator::ALPHABET, '/').']+$/',
            substr($id, strlen($prefix->value) + 6),
        );
    }

    /**
     * @return array<string, array{0: TransactionIdPrefix, 1: string, 2: int}>
     */
    public static function prefixFormats(): array
    {
        return [
            'pay bill ftth' => [TransactionIdPrefix::PayBillFtth, '261109', 6],
            'pay bill cg' => [TransactionIdPrefix::PayBillCg, '261109', 6],
            'buy package' => [TransactionIdPrefix::BuyPackage, '261109', 7],
            'top up mapping' => [TransactionIdPrefix::TopUp, '261109', 7],
        ];
    }

    public function test_pay_bill_service_type_mapping(): void
    {
        $this->assertSame(TransactionIdPrefix::PayBillCg, TransactionIdPrefix::forPayBill('CG'));
        $this->assertSame(TransactionIdPrefix::PayBillCg, TransactionIdPrefix::forPayBill('cg'));
        $this->assertSame(TransactionIdPrefix::PayBillFtth, TransactionIdPrefix::forPayBill('FTTH'));
    }

    public function test_successive_ids_are_not_identical(): void
    {
        $generator = new TransactionIdGenerator;
        $seen = [];

        for ($i = 0; $i < 20; $i++) {
            $id = $generator->generate(TransactionIdPrefix::BuyPackage);
            $this->assertArrayNotHasKey($id, $seen);
            $seen[$id] = true;
        }
    }
}
