<?php

namespace App\Http\Controllers\ChangePassword;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePassword\ChangePasswordRequest as StoreChangePasswordRequest;
use App\Http\Requests\ChangePassword\VerifyChangePasswordOtpRequest;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class ChangePasswordController extends Controller
{
    public function store(StoreChangePasswordRequest $request, OtpService $otp): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $challenge = $otp->request($user->phone, (string) $request->ip());

        Cache::store(config('otp.cache_store', 'redis'))->put(
            $this->pendingPasswordKey($challenge['challenge_id']),
            [
                'user_id' => $user->id,
                'password' => Hash::make($request->validated('new_password')),
            ],
            (int) config('otp.challenge_ttl'),
        );

        return response()->json($challenge, 202);
    }

    public function verifyOtp(VerifyChangePasswordOtpRequest $request, OtpService $otp): JsonResponse
    {
        $challengeId = $request->string('challenge_id')->toString();
        $verificationToken = $otp->verify($challengeId, $request->string('code')->toString());

        return $otp->consumeVerificationToken($verificationToken, function (string $phone) use (
            $request,
            $challengeId,
        ): JsonResponse {
            $user = $request->user();
            abort_unless($user instanceof User && $user->phone === $phone, 403);

            $cache = Cache::store(config('otp.cache_store', 'redis'));
            $pendingPassword = $cache->pull($this->pendingPasswordKey($challengeId));
            abort_unless(
                is_array($pendingPassword) &&
                    (int) ($pendingPassword['user_id'] ?? 0) === $user->id &&
                    is_string($pendingPassword['password'] ?? null),
                422,
                'The password change request is invalid or expired.',
            );

            $user->forceFill(['password' => $pendingPassword['password']])->save();

            return response()->json(['message' => 'Password changed successfully.']);
        });
    }

    private function pendingPasswordKey(string $challengeId): string
    {
        return 'auth:change-password:pending:' . hash('sha256', $challengeId);
    }
}
