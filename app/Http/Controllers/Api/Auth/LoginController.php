<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\ApiAuthenticationService;
use App\Services\DeviceToken\DeviceTokenService;
use App\Services\SecurityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use App\Models\User;

class LoginController extends Controller
{
    public function __construct(protected SecurityLogService $securityLogService) {}
    public function __invoke(LoginRequest $request, ApiAuthenticationService $service): JsonResponse
    {
        $phone = $request->string('phone')->toString();

        try {
            $result = $service->login($phone, $request->string('password')->toString(), $request->ip());
        } catch (ValidationException | HttpExceptionInterface $e) {
            $targetUser = User::where('phone', $phone)->first();
            $this->securityLogService->recordLoginFailure(
                guard: 'user',
                identity: $phone,
                actor: $targetUser,
                metadata: [
                    'phone' => $phone,
                    'reason' =>
                        $e instanceof HttpExceptionInterface && $e->getStatusCode() === 429
                            ? 'Too many login attempts'
                            : 'Invalid credentials or inactive account',
                ],
                request: $request,
            );

            throw $e;
        }

        $this->securityLogService->clearLoginFailures('user', $phone);

        DeviceTokenService::register(
            $result['user'],
            $request->string('device_token')->toString() !== '' ? $request->string('device_token')->toString() : null,
            $request->input('platform'),
        );

        $this->logActivity($request, 'login_success', [
            'user_id' => $result['user']->id,
            'phone' => $result['user']->phone ?? null,
        ]);

        return response()->json([
            'token' => $result['token'],
            'user' => new UserResource($result['user']),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->user()->tokens()->delete();
        $request->user()->deviceTokens()->delete();
        $this->logActivity($request, 'logout', [
            'user_id' => $user->id,
        ]);

        return response()->json(['message' => 'Logged out successfully.']);
    }
}
