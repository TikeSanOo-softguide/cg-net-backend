<?php

namespace App\Http\Controllers;
use App\Models\UserLog;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function logActivity(Request $request, $action, $extraData = [])
    {
        $userId = $request->user() ? $request->user()->id : null;

        if (!$userId && isset($extraData['user_id'])) {
            $userId = $extraData['user_id'];
        }

        UserLog::create([
            'user_id' => $userId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => array_merge(['action' => $action], $extraData),
        ]);
    }
}
