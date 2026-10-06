<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\CompleteRegistrationRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\ApiAuthenticationService;
use App\Services\DeviceToken\DeviceTokenService;
use Illuminate\Http\JsonResponse;

class RegistrationController extends Controller
{
    /** New account, after POST /auth/otp/verify returned next_step=register. */
    public function __invoke(CompleteRegistrationRequest $request, ApiAuthenticationService $service): JsonResponse
    {
        $result = $service->completeRegistration(
            $request->string('verification_token')->toString(),
            $request->validated(),
        );

        DeviceTokenService::register(
            $result['user'],
            $request->string('device_token')->toString() !== '' ? $request->string('device_token')->toString() : null,
            $request->input('platform'),
        );

        $this->logActivity($request, 'register_complete', [
            'user_id' => $result['user']->id,
            'name' => $result['user']->name,
        ]);

        return response()->json(
            [
                'token' => $result['token'],
                'token_type' => 'Bearer',
                'expires_at' => $result['expires_at']->toIso8601String(),
                'user' => new UserResource($result['user']),
            ],
            201,
        );
    }
}
