<?php

namespace App\Support;

use Illuminate\Support\Carbon;

final class DateTimeInput
{
    public static function normalizeToApplicationTimezone(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $timezone = (string) config('app.timezone', 'UTC');

        return Carbon::parse($value, $timezone)
            ->setTimezone($timezone)
            ->format('Y-m-d H:i:s');
    }
}
