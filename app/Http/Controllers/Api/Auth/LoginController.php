<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\MessageCode;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\SessionCheckRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\ApiAuthenticationService;
use App\Services\DeviceToken\DeviceTokenService;
use App\Services\SecurityLog\SecurityLogService;
use App\Support\ApiMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class LoginController extends Controller
{
    public function __construct(protected SecurityLogService $securityLogService) {}

    public function __invoke(LoginRequest $request, ApiAuthenticationService $service): JsonResponse
    {
        $verificationToken = $request->string('verification_token')->toString();
        $phone = $service->verifiedPhone($verificationToken);

        try {
            $result = $service->login($verificationToken, $request->string('password')->toString(), $request->ip());
        } catch (ValidationException | HttpExceptionInterface $e) {
            $reason = match (true) {
                $e instanceof HttpExceptionInterface && $e->getStatusCode() === 429 => 'Too many login attempts',
                $e instanceof HttpExceptionInterface && $e->getStatusCode() === 409
                    => 'Account already has an active session',
                default => 'Invalid credentials or inactive account',
            };

            $this->securityLogService->recordLoginFailure(
                guard: 'user',
                identity: $phone,
                actor: User::where('phone', $phone)->first(),
                metadata: [
                    'phone' => $phone,
                    'reason' => $reason,
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
            'phone' => $result['user']->phone,
            'method' => 'password',
        ]);

        return response()->json([
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_at' => $result['expires_at']->toIso8601String(),
            'user' => new UserResource($result['user']),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->delete();
        $user->deviceTokens()->delete();
        $this->logActivity($request, 'logout', [
            'user_id' => $user->id,
        ]);

        return response()->json(ApiMessage::attach(MessageCode::AuthLoggedOut));
    }

    public function checkSession(SessionCheckRequest $request): JsonResponse
    {
        $user = User::query()->where('phone', $request->validated('phone'))->first();
        $showAlert =
            $user
                ?->tokens()
                ->where(fn($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists() ?? false;

        if ($user !== null && $user->status == UserStatus::Deactivated) {
            return response()->json(
                ApiMessage::attach(MessageCode::UserDeactivated, [
                    'show_alert' => $showAlert,
                ]),
                403,
            );
        }
        if ($user !== null && $user->status == UserStatus::Suspended) {
            return response()->json(
                ApiMessage::attach(MessageCode::UserSuspended, [
                    'show_alert' => $showAlert,
                ]),
                403,
            );
        }

        return response()->json([
            'show_alert' => $showAlert,
        ]);
    }
}
