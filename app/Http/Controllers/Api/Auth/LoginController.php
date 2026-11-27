<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\MessageCode;
use App\Enums\UserLogEvent;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\SessionCheckRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\UserLog;
use App\Services\Auth\ApiAuthenticationService;
use App\Services\DeviceToken\DeviceTokenService;
use App\Services\SecurityLog\SecurityLogService;
use App\Support\ApiMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class LoginController extends Controller
{
    public function __construct(protected SecurityLogService $securityLogService) {}

    public function __invoke(LoginRequest $request, ApiAuthenticationService $service): JsonResponse
    {
        $verificationToken = $request->string('verification_token')->toString();
        $deviceId = $request->string('device_id')->toString();
        $phone = $service->verifiedPhone($verificationToken);

        try {
            $result = $service->login(
                $verificationToken,
                $request->string('password')->toString(),
                $request->ip(),
                $request->string('device_id')->toString(),
            );
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
        DB::transaction(function () use ($request, $user): void {
            $user->tokens()->delete();
            $user->deviceTokens()->delete();
            $this->logActivity($request, UserLogEvent::Logout, [
                'user_id' => $user->id,
            ]);
        });

        return response()->json(ApiMessage::attach(MessageCode::AuthLoggedOut));
    }

    public function checkSession(SessionCheckRequest $request): JsonResponse
    {
        $user = User::query()->where('phone', $request->validated('phone'))->first();
        $deviceHash = hash('sha256', $request->validated('device_id'));

        $isBound = $user?->device_id !== null;
        $sameDevice = $isBound && hash_equals($user->device_id, $deviceHash);

        $hasActiveToken =
            $user
                ?->tokens()
                ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists() ?? false;

        $showAlert = $hasActiveToken && !$sameDevice;
        $payload = [
            'show_alert' => $showAlert,
            'device_name' => $showAlert ? $this->signedInDeviceName($user) : null,
        ];

        if ($user !== null && $user->status == UserStatus::Deactivated) {
            return response()->json(ApiMessage::attach(MessageCode::UserDeactivated, $payload), 403);
        }
        if ($user !== null && $user->status == UserStatus::Suspended) {
            return response()->json(ApiMessage::attach(MessageCode::UserSuspended, $payload), 403);
        }

        if ($isBound && !$sameDevice) {
            return response()->json(ApiMessage::attach(MessageCode::AuthAlreadySignedIn, $payload), 403);
        }

        if ($showAlert) {
            return response()->json(ApiMessage::attach(MessageCode::AuthAlreadySignedIn, $payload));
        }

        return response()->json($payload);
    }

    private function signedInDeviceName(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $log = UserLog::query()
            ->where('user_id', $user->id)
            ->whereIn('event', [UserLogEvent::LoginSuccess->value, UserLogEvent::RegisterComplete->value])
            ->latest('created_at')
            ->first();
        $name = is_array($log?->metadata) ? $log->metadata['device_name'] ?? null : null;
        $name = is_string($name) ? trim($name) : '';

        return $name === '' ? null : $name;
    }
}
