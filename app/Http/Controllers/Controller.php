<?php

namespace App\Http\Controllers;

use App\Enums\UserLogEvent;
use App\Models\UserLog;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function logActivity(Request $request, UserLogEvent $action, array $extraData = []): void
    {
        $userId = $request->user() ? $request->user()->id : null;

        if (!$userId && isset($extraData['user_id'])) {
            $userId = $extraData['user_id'];
        }

        $metadata = [];

        foreach ($extraData as $key => $value) {
            if ($key === 'user_id' || ! is_string($key)) {
                continue;
            }

            $normalized = $this->normalizeLogValue($value);
            if ($normalized === null) {
                continue;
            }

            $metadata[$key] = $normalized;
        }

        foreach ($this->deviceMetadata($request) as $key => $value) {
            $metadata[$key] = $value;
        }

        UserLog::create([
            'user_id' => $userId,
            'event' => $action->value,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => $metadata === [] ? new \stdClass() : $metadata,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function deviceMetadata(Request $request): array
    {
        $metadata = [];
        $deviceId = $this->cleanDeviceValue($request->header('X-Device-Id'), 64);
        $deviceName = $this->cleanDeviceValue($request->header('X-Device-Name'), 120);
        $platform = strtolower($this->cleanDeviceValue($request->header('X-Platform'), 16) ?? '');

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

    private function cleanDeviceValue(mixed $value, int $max): ?string
    {
        $value = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $value) ?? '');
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }

    private function normalizeLogValue(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return null;
    }
}
