<?php

namespace App\Http\Controllers\Api\DeviceToken;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DeviceToken\DestroyDeviceTokenRequest;
use App\Http\Requests\Api\DeviceToken\StoreDeviceTokenRequest;
use App\Services\DeviceToken\DeviceTokenService;
use Illuminate\Http\JsonResponse;

class DeviceTokenController extends Controller
{
    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();

        DeviceTokenService::register($request->user(), $validated['token'], $validated['platform'] ?? null);

        return response()->json(['message' => 'Device token registered.']);
    }

    public function destroy(DestroyDeviceTokenRequest $request): JsonResponse
    {
        $request->user()->deviceTokens()->where('token', $request->validated('token'))->delete();

        return response()->json(['message' => 'Device token removed.']);
    }
}
