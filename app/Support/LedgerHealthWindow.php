<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class LedgerHealthWindow
{
    public const CLOSE_HOUR = 16;

    /**
     * Closing window (start, end] at the configured close hour, returned as UTC instants.
     * End is today at the configured close hour; start is the previous day at that hour.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function forCloseDate(?string $closeDate = null): array
    {
        $timezone = (string) config('app.timezone', 'UTC');
        $endLocal = $closeDate
            ? Carbon::parse($closeDate, $timezone)->setTime(self::CLOSE_HOUR, 0, 0)
            : Carbon::now($timezone)->setTime(self::CLOSE_HOUR, 0, 0);

        $startLocal = $endLocal->copy()->subDay();

        return [$startLocal->utc(), $endLocal->utc()];
    }
}
