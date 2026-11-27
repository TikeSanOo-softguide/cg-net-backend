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
        $bodyDeviceId = $request->input('device_id');
        $deviceIdInput = is_string($bodyDeviceId) && trim($bodyDeviceId) !== ''
            ? $bodyDeviceId
            : $request->header('X-Device-Id');
        $deviceId = self::clean($deviceIdInput, 65);
        $deviceName = self::clean($request->header('X-Device-Name'), 120);
        $platform = strtolower(self::clean($request->header('X-Platform'), 16) ?? '');

        if ($deviceId !== null && mb_strlen($deviceId) <= 64 && preg_match('/^[A-Za-z0-9:_-]{8,64}$/', $deviceId) === 1) {
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
