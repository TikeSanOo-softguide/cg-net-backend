<?php

namespace Tests\Unit;

use App\Support\DateTimeInput;
use Tests\TestCase;

class DateTimeInputTest extends TestCase
{
    public function test_it_normalizes_offset_timestamps_to_the_application_timezone(): void
    {
        config(['app.timezone' => 'Asia/Yangon']);

        $this->assertSame(
            '2026-10-01 16:30:00',
            DateTimeInput::normalizeToApplicationTimezone('2026-10-01T10:00:00Z'),
        );
    }

    public function test_it_preserves_timezone_free_wall_times_in_the_application_timezone(): void
    {
        config(['app.timezone' => 'Asia/Yangon']);

        $this->assertSame(
            '2026-10-01 10:00:00',
            DateTimeInput::normalizeToApplicationTimezone('2026-10-01T10:00:00'),
        );
    }

    public function test_empty_datetime_values_remain_null(): void
    {
        $this->assertNull(DateTimeInput::normalizeToApplicationTimezone(null));
        $this->assertNull(DateTimeInput::normalizeToApplicationTimezone(' '));
    }
}
