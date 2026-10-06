<?php

namespace App\Http\Controllers\Api\Auth;

use App\Exceptions\Auth\OtpThrottledException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RequestOtpRequest;
use App\Http\Requests\Api\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\ApiAuthenticationService;
use App\Services\DeviceToken\DeviceTokenService;
use App\Services\SecurityLog\SecurityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;

class OtpController extends Controller
{
    public function __construct(protected SecurityLogService $securityLogService) {}

    public function request(RequestOtpRequest $request, ApiAuthenticationService $service): JsonResponse
    {
        $phone = $request->string('phone')->toString();
        $requestLimit = (int) config('otp.rate_limits.otp_request.max_attempts', 3);
        $decaySeconds = (int) config('otp.rate_limits.otp_request.decay_seconds', 3600);

        $limiterKey = 'auth:otp:security:request:' . hash('sha256', $phone);

        // The hourly allowance counts codes actually sent. Check it without spending
        // it here; taps rejected by the resend cooldown never reach the hit below.
        if (RateLimiter::tooManyAttempts($limiterKey, $requestLimit)) {
            $retryAfter = RateLimiter::availableIn($limiterKey);

            $this->securityLogService->record(
                event: 'otp_request_limit_exceeded',
                actor: User::withTrashed()->where('phone', $phone)->first(),
                metadata: [
                    'phone' => $phone,
                    'request_count' => RateLimiter::attempts($limiterKey),
                    'limit' => $requestLimit,
                    'reason' => 'Excessive OTP requests (> ' . $requestLimit . ' times)',
                ],
                request: $request,
            );

            return $this->throttled('Too many OTP requests. Please try again later.', 'hourly_limit', $retryAfter);
        }

        try {
            $result = $service->requestOtp($phone, $request->ip());
        } catch (OtpThrottledException $exception) {
            return $this->throttled($exception->getMessage(), $exception->reason, $exception->retryAfter);
        }

        RateLimiter::hit($limiterKey, $decaySeconds);

        $response = [
            'challenge_id' => $result['challenge_id'],
            // Seconds the app should keep the "Resend" button disabled.
            'resend_after' => (int) config('otp.resend_cooldown'),
        ];

        if ($result['debug_otp'] !== null) {
            $response['debug_otp'] = $result['debug_otp'];
        }

        return response()->json($response, 202);
    }

    /**
     * 429 body the app can act on: `retry_after` is the number of seconds left to wait
     * (also sent as the Retry-After header) and `reason` says which limit applies:
     * resend_cooldown (short, per phone), hourly_limit (per phone), rate_limited (per IP).
     */
    private function throttled(string $message, string $reason, int $retryAfter): JsonResponse
    {
        $retryAfter = max(1, $retryAfter);

        return response()
            ->json(['message' => $message, 'reason' => $reason, 'retry_after' => $retryAfter], 429)
            ->header('Retry-After', (string) $retryAfter);
    }

    /**
     * Response always contains `next_step`:
     *   password       -> existing account, call POST /auth/login with verification_token
     *   register       -> new account, call POST /auth/register with verification_token
     *   authenticated  -> password step disabled, `token` + `user` are returned
     */
    public function verify(VerifyOtpRequest $request, ApiAuthenticationService $service): JsonResponse
    {
        $result = $service->verifyOtp(
            $request->string('challenge_id')->toString(),
            $request->string('code')->toString(),
        );

        $payload = ['next_step' => $result['next_step']];

        if (isset($result['account_status'])) {
            $payload['account_status'] = $result['account_status'];
        }

        if (isset($result['verification_token'])) {
            $payload['verification_token'] = $result['verification_token'];

            return response()->json($payload);
        }

        DeviceTokenService::register(
            $result['user'],
            $request->string('device_token')->toString() !== '' ? $request->string('device_token')->toString() : null,
            $request->input('platform'),
        );

        $this->logActivity($request, 'login_success', [
            'user_id' => $result['user']->id,
            'phone' => $result['user']->phone,
            'method' => 'otp',
        ]);

        return response()->json([
            ...$payload,
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_at' => $result['expires_at']->toIso8601String(),
            'user' => new UserResource($result['user']),
        ]);
    }
}
