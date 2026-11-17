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

        UserLog::create([
            'user_id' => $userId,
            'event' => $action->value,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => new \stdClass(),
        ]);
    }
}
