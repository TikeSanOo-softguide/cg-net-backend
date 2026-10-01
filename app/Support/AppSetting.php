<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

final class AppSetting
{
    public const LEDGER_HEALTH_DAILY_SCAN_ENABLED = 'ledger_health.daily_scan_enabled';

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever(self::cacheKey($key), function () use ($key, $default) {
            $setting = Setting::query()->where('key', $key)->first();

            if ($setting === null) {
                return $default;
            }

            return self::decode($setting->value);
        });
    }

    public static function put(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => self::encode($value)],
        );

        Cache::forget(self::cacheKey($key));
        Cache::forever(self::cacheKey($key), $value);
    }

    public static function boolean(string $key, bool $default = false): bool
    {
        return filter_var(self::get($key, $default), FILTER_VALIDATE_BOOLEAN);
    }

    private static function cacheKey(string $key): string
    {
        return "app_setting:{$key}";
    }

    private static function encode(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private static function decode(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return '';
        }

        if ($trimmed === '1' || strcasecmp($trimmed, 'true') === 0) {
            return true;
        }

        if ($trimmed === '0' || strcasecmp($trimmed, 'false') === 0) {
            return false;
        }

        if (is_numeric($trimmed)) {
            return str_contains($trimmed, '.') ? (float) $trimmed : (int) $trimmed;
        }

        $json = json_decode($trimmed, true);

        return json_last_error() === JSON_ERROR_NONE ? $json : $value;
    }
}
