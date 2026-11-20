<?php

namespace App\Support;

use Illuminate\Http\Request;

class DeviceRequestMetadata
{
    /**
     * @return array<string, string>
     */
    public static function from(Request $request): array
    {
        $metadata = [];
        $deviceId = self::clean($request->header('X-Device-Id'), 64);
        $deviceName = self::clean($request->header('X-Device-Name'), 120);
        $platform = strtolower(self::clean($request->header('X-Platform'), 16) ?? '');

        if ($deviceId !== null && preg_match('/^[A-Za-z0-9-]{8,64}$/', $deviceId) === 1) {
            $metadata['device_id'] = $deviceId;
        }

        if ($deviceName !== null) {
            $metadata['device_name'] = $deviceName;
        }

        if (in_array($platform, ['android', 'ios', 'web'], true)) {
            $metadata['platform'] = $platform;
        }

        return $metadata;
    }

    private static function clean(mixed $value, int $max): ?string
    {
        $value = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $value) ?? '');
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }
}
