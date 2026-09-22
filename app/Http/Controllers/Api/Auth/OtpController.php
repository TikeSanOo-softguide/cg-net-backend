<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RegisterRequestOtpRequest;
use App\Http\Requests\Api\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\ApiAuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OtpController extends Controller
{
    public function request(RegisterRequestOtpRequest $request, ApiAuthenticationService $service): JsonResponse
    {
        return $this->sendChallenge($request, $service);
    }

    public function resend(RegisterRequestOtpRequest $request, ApiAuthenticationService $service): JsonResponse
    {
        return $this->sendChallenge($request, $service);
    }

    public function verify(VerifyOtpRequest $request, ApiAuthenticationService $service): JsonResponse
    {
        $result = $service->verifyLoginOtp(
            $request->string('challenge_id')->toString(),
            $request->string('code')->toString(),
        );

        return response()->json([
            'token' => $result['token'],
            'user' => new UserResource($result['user']),
        ]);
    }

    private function sendChallenge(RegisterRequestOtpRequest $request, ApiAuthenticationService $service): JsonResponse
    {
        try {
            $result = $service->requestLoginOtp(
                $request->string('phone')->toString(),
                $request->ip(),
            );
        } catch (RuntimeException $exception) {
            Log::warning('OTP provider request failed.', ['provider' => config('otp.provider')]);

            return response()->json(['message' => 'The verification service is temporarily unavailable.'], 503);
        }

        $response = ['challenge_id' => $result['challenge_id']];

        if ($result['debug_otp'] !== null) {
            $response['debug_otp'] = $result['debug_otp'];
        }

        return response()->json($response, 202);
    }
}
