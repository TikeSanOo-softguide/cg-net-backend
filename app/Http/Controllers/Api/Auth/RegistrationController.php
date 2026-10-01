<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\CompleteRegistrationRequest;
use App\Http\Requests\Api\Auth\RegisterRequestOtpRequest;
use App\Http\Requests\Api\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\ApiAuthenticationService;
use App\Services\DeviceToken\DeviceTokenService;
use App\Services\SecurityLog\SecurityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\User;

class RegistrationController extends Controller
{
    public function __construct(protected SecurityLogService $securityLogService) {}
    public function requestOtp(RegisterRequestOtpRequest $request, ApiAuthenticationService $service): JsonResponse
    {
        $phone = $request->string('phone')->toString();
        $requestLimit = (int) config('otp.rate_limits.otp_request.max_attempts', 3);
        $decaySeconds = (int) config('otp.rate_limits.otp_request.decay_seconds', 3600);

        $limiterKey = 'auth:otp:security:request:' . hash('sha256', $phone);
        $requestCount = RateLimiter::hit($limiterKey, $decaySeconds);

        if ($requestCount === $requestLimit + 1) {
            $this->securityLogService->record(
                event: 'otp_requested',
                actor: User::withTrashed()->where('phone', $phone)->first(),
                metadata: [
                    'phone' => $phone,
                    'request_count' => $requestCount,
                    'limit' => $requestLimit,
                    'reason' => 'Excessive OTP requests (> 3 times)',
                ],
                request: $request,
            );
        }

        if ($requestCount > $requestLimit + 1) {
            return response()->json(
                [
                    'message' => 'Too many OTP requests. Please try again later.',
                ],
                429,
            );
        }

        $result = $service->requestRegistrationOtp($phone, $request->ip());
        $response = ['challenge_id' => $result['challenge_id']];

        if ($result['debug_otp'] !== null) {
            $response['debug_otp'] = $result['debug_otp'];
        }

        return response()->json($response, 202);
    }

    public function verifyOtp(VerifyOtpRequest $request, ApiAuthenticationService $service): JsonResponse
    {
        $verificationToken = $service->verifyRegistrationOtp(
            $request->string('challenge_id')->toString(),
            $request->string('code')->toString(),
        );

        return response()->json([
            'verification_token' => $verificationToken,
        ]);
    }

    public function complete(CompleteRegistrationRequest $request, ApiAuthenticationService $service): JsonResponse
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
            'name' => $result['user']->name ?? null,
            'email' => $result['user']->email ?? null,
        ]);

        return response()->json(
            [
                'token' => $result['token'],
                'user' => new UserResource($result['user']),
            ],
            201,
        );
    }
}
