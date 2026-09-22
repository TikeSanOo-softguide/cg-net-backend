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
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class OtpController extends Controller
{
    public function request(RegisterRequestOtpRequest $request, ApiAuthenticationService $service): JsonResponse
    {
        return $this->sendChallenge($request, $service, true);
    }

    public function resend(RegisterRequestOtpRequest $request, ApiAuthenticationService $service): JsonResponse
    {
        return $this->sendChallenge($request, $service, true);
    }

    public function verify(VerifyOtpRequest $request, ApiAuthenticationService $service): JsonResponse
    {
        $result = $service->verifyCustomerOtp(
            $request->string('challenge_id')->toString(),
            $request->string('code')->toString(),
            $request->input('flow'),
        );

        return response()->json($result);
    }

    private function sendChallenge(
        RegisterRequestOtpRequest $request,
        ApiAuthenticationService $service,
        bool $customerFlow = false,
    ): JsonResponse
    {
        try {
            $result = $customerFlow
                ? $service->requestCustomerOtp(
                    $request->string('phone')->toString(),
                    $request->ip(),
                )
                : $service->requestLoginOtp(
                $request->string('phone')->toString(),
                $request->ip(),
            );
        } catch (TooManyRequestsHttpException $exception) {
            $retryAfter = $exception->getHeaders()['Retry-After'] ?? $exception->getHeaders()['retry-after'] ?? null;

            return response()->json(
                ['message' => $exception->getMessage()],
                429,
                $retryAfter === null ? [] : ['Retry-After' => (string) $retryAfter],
            );
        } catch (RuntimeException $exception) {
            Log::warning('OTP provider request failed.', [
                'provider' => config('otp.provider'),
                'message' => $exception->getMessage(),
                'previous' => $exception->getPrevious()?->getMessage(),
            ]);

            return response()->json(['message' => 'The verification service is temporarily unavailable.'], 503);
        }

        $response = [
            'challenge_id' => $result['challenge_id'],
            ...($customerFlow ? ['flow' => $result['flow']] : []),
        ];

        if ($result['debug_otp'] !== null) {
            $response['debug_otp'] = $result['debug_otp'];
        }

        return response()->json($response, 202);
    }
}
