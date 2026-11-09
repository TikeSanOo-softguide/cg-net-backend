<?php

namespace Tests\Unit;

use App\Services\Order\OrderNumberGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class OrderNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_generated_order_numbers_use_ord_prefix_date_and_padded_sequence(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-09 15:00:00'));

        $generator = app(OrderNumberGenerator::class);
        $first = $generator->generate();
        $second = $generator->generate();

        $this->assertSame(OrderNumberGenerator::LENGTH, strlen($first));
        $this->assertSame('ORD2611090000001', $first);
        $this->assertSame('ORD2611090000002', $second);
        $this->assertTrue($generator->matches($first));
        $this->assertTrue($generator->matches($second));
        $this->assertSame('0000001', substr($first, 9));
        $this->assertSame('0000002', substr($second, 9));
    }

    public function test_daily_sequence_resets_on_a_new_date(): void
    {
        $generator = app(OrderNumberGenerator::class);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-09 23:59:00'));
        $this->assertSame('ORD2611090000001', $generator->generate());
        $this->assertSame('ORD2611090000002', $generator->generate());

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-10 00:00:00'));
        $this->assertSame('ORD2611100000001', $generator->generate());
    }

    public function test_sequence_uses_the_application_timezone(): void
    {
        $previous = (string) config('app.timezone');
        config(['app.timezone' => 'Asia/Yangon']);
        date_default_timezone_set('Asia/Yangon');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-09 06:30:00', 'Asia/Yangon'));

        try {
            $this->assertSame('ORD2611090000001', app(OrderNumberGenerator::class)->generate());
        } finally {
            config(['app.timezone' => $previous]);
            date_default_timezone_set($previous);
        }
    }

    public function test_rolled_back_transactions_do_not_consume_the_sequence(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-09 12:00:00'));
        $generator = app(OrderNumberGenerator::class);

        try {
            DB::transaction(function () use ($generator): void {
                $this->assertSame('ORD2611090000001', $generator->generate());

                throw new RuntimeException('force rollback');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('force rollback', $exception->getMessage());
        }

        $this->assertSame('ORD2611090000001', $generator->generate());
    }

    public function test_exhausted_daily_sequence_is_rejected(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-09 12:00:00'));

        DB::table('order_number_sequences')->insert([
            'sequence_date' => '2026-11-09',
            'next_number' => OrderNumberGenerator::MAX_DAILY_SEQUENCE + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The daily order number sequence is exhausted.');

        app(OrderNumberGenerator::class)->generate();
    }
}
