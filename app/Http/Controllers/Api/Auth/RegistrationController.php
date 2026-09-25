<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\CompleteRegistrationRequest;
use App\Http\Requests\Api\Auth\RegisterRequestOtpRequest;
use App\Http\Requests\Api\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\ApiAuthenticationService;
use Illuminate\Http\JsonResponse;

class RegistrationController extends Controller
{
    public function requestOtp(RegisterRequestOtpRequest $request, ApiAuthenticationService $service): JsonResponse
    {
        $result = $service->requestRegistrationOtp($request->string('phone')->toString(), $request->ip());
        $response = ['challenge_id' => $result['challenge_id']];

        if ($result['debug_otp'] !== null) {
            $response['debug_otp'] = $result['debug_otp'];
        }

        return response()->json($response, 202);
    }

    public function verifyOtp(VerifyOtpRequest $request, ApiAuthenticationService $service): JsonResponse
    {
        return response()->json([
            'verification_token' => $service->verifyRegistrationOtp(
                $request->string('challenge_id')->toString(),
                $request->string('code')->toString(),
            ),
        ]);
    }

    public function complete(CompleteRegistrationRequest $request, ApiAuthenticationService $service): JsonResponse
    {
        $result = $service->completeRegistration(
            $request->string('verification_token')->toString(),
            $request->validated(),
        );

        return response()->json(
            [
                'token' => $result['token'],
                'user' => new UserResource($result['user']),
            ],
            201,
        );
    }
}
