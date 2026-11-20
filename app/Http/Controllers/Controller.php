<?php

namespace App\Http\Controllers;

use App\Enums\UserLogEvent;
use App\Models\UserLog;
use App\Support\DeviceRequestMetadata;
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
        return DeviceRequestMetadata::from($request);
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
