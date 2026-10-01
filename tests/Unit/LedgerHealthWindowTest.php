<?php

namespace Tests\Unit;

use App\Support\LedgerHealthWindow;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LedgerHealthWindowTest extends TestCase
{
    public function test_close_date_window_is_previous_day_4pm_to_close_day_4pm_yangon(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 20:00:00', 'UTC'));

        [$start, $end] = LedgerHealthWindow::forCloseDate('2026-10-01');

        $this->assertSame(
            '2026-09-30 16:00:00',
            $start->clone()->timezone('Asia/Yangon')->format('Y-m-d H:i:s'),
        );
        $this->assertSame(
            '2026-10-01 16:00:00',
            $end->clone()->timezone('Asia/Yangon')->format('Y-m-d H:i:s'),
        );
        $this->assertTrue($start->isUtc());
        $this->assertTrue($end->isUtc());
    }

    public function test_default_window_uses_today_4pm_yangon_as_end(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 09:30:00', 'Asia/Yangon'));

        [$start, $end] = LedgerHealthWindow::forCloseDate();

        $this->assertSame(
            '2026-09-30 16:00:00',
            $start->clone()->timezone('Asia/Yangon')->format('Y-m-d H:i:s'),
        );
        $this->assertSame(
            '2026-10-01 16:00:00',
            $end->clone()->timezone('Asia/Yangon')->format('Y-m-d H:i:s'),
        );
    }
}
