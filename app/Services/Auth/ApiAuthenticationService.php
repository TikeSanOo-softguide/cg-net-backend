<?php

namespace App\Services\Auth;

use App\Enums\UserStatus;
use App\Enums\WalletStatus;
use App\Models\User;
use App\Services\Auth\OtpService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class ApiAuthenticationService
{
    private const RESTRICTED_USER_STATUSES = [UserStatus::Suspended];

    private const RESTRICTED_WALLET_STATUSES = [WalletStatus::Suspended, WalletStatus::Frozen, WalletStatus::Inactive];

    public function __construct(private readonly OtpService $otp) {}

    public function requestRegistrationOtp(string $phone, string $ip): array
    {
        $phoneIsRegistered = User::query()->where('phone', $phone)->exists();

        return $this->otp->request($phone, $ip, !$phoneIsRegistered);
    }

    public function requestLoginOtp(string $phone, string $ip): array
    {
        return $this->otp->request($phone, $ip, true, 'login');
    }

    /** @return array{challenge_id: string, debug_otp: ?string, flow: string} */
    public function requestCustomerOtp(string $phone, string $ip): array
    {
        $flow = $this->userByPhone($phone, true) !== null
            ? 'login'
            : 'registration';
        $result = $this->otp->request($phone, $ip, true, $flow);

        return [...$result, 'flow' => $flow];
    }

    public function verifyRegistrationOtp(string $challengeId, string $code): string
    {
        return $this->otp->verify($challengeId, $code);
    }

    /** @return array{user: User, token: string} */
    public function verifyLoginOtp(string $challengeId, string $code): array
    {
        $phone = $this->otp->verify($challengeId, $code, 'login');
        $user = $this->userByPhone($phone);

        if (!$user || $user->status !== UserStatus::Active) {
            abort(422, 'The verification code is invalid or the account is unavailable.');
        }

        return ['user' => $user, 'token' => $this->createToken($user)];
    }

    /** @return array{flow: string, token?: string, verification_token?: string} */
    public function verifyCustomerOtp(string $challengeId, string $code, ?string $flow = null): array
    {
        $purpose = $this->otp->purposeFor($challengeId);

        if ($purpose === 'registration') {
            return [
                'flow' => $purpose,
                'verification_token' => $this->verifyRegistrationOtp($challengeId, $code),
            ];
        }

        $result = $this->verifyLoginOtp($challengeId, $code);

        return ['flow' => $purpose, 'token' => $result['token']];
    }

    /** @return array{user: User, token: string} */
    public function completeRegistration(string $token, array $data): array
    {
        return $this->otp->consumeVerificationToken($token, function (string $phone) use ($data): array {
            try {
                return DB::transaction(function () use ($phone, $data): array {
                    $existingUser = $this->userByPhone($phone, true);

                    if ($existingUser && !$existingUser->trashed()) {
                        abort(422, 'The registration details are already in use.');
                    }

                    if ($existingUser) {
                        return $this->restoreExistingUser($existingUser, $data);
                    }

                    return $this->createNewUser($phone, $data);
                });
            } catch (QueryException $exception) {
                if ((string) $exception->getCode() === '23000') {
                    abort(422, 'The registration details are already in use.');
                }

                throw $exception;
            }
        });
    }

    /** @return array{user: User, token: string} */
    public function login(string $phone, string $password, string $ip): array
    {
        $user = User::query()->where('phone', $phone)->first();
        $accountKey = $user?->exists
            ? 'auth:login:user:' . hash('sha256', $user->getKey() . '|' . $phone)
            : 'auth:login:user:' . hash('sha256', $phone);
        $ipKey = 'auth:login:ip:' . hash('sha256', $ip);
        $accountMaxAttempts = (int) config('otp.rate_limits.login.max_attempts');
        $ipMaxAttempts = (int) config('otp.rate_limits.login_ip.max_attempts', $accountMaxAttempts);
        $decaySeconds = (int) config('otp.rate_limits.login.decay_seconds');
        $ipDecaySeconds = (int) config('otp.rate_limits.login_ip.decay_seconds', $decaySeconds);

        if (
            RateLimiter::tooManyAttempts($accountKey, $accountMaxAttempts) ||
            RateLimiter::tooManyAttempts($ipKey, $ipMaxAttempts)
        ) {
            throw new TooManyRequestsHttpException(
                max(RateLimiter::availableIn($accountKey), RateLimiter::availableIn($ipKey)),
                'Too many requests.',
            );
        }

        RateLimiter::hit($accountKey, $decaySeconds);
        RateLimiter::hit($ipKey, $ipDecaySeconds);

        if (!$user || $user->status !== UserStatus::Active || !Hash::check($password, $user->getAuthPassword())) {
            abort(422, 'The provided credentials are incorrect.');
        }

        RateLimiter::clear($accountKey);
        RateLimiter::clear($ipKey);
        $user->tokens()->delete();

        return ['user' => $user, 'token' => $this->createToken($user)];
    }

    private function restoreExistingUser(User $existingUser, array $data): array
    {
        $wallet = $existingUser->wallet()->withTrashed()->first();

        if ($this->isRestrictedForReRegistration($existingUser, $wallet)) {
            abort(422, 'This account is restricted and cannot be re-registered.');
        }

        $existingUser->restore();
        $existingUser->fill([
            'name' => $data['name'],
            'password' => $data['password'],
            'status' => UserStatus::Active,
        ]);
        $existingUser->save();

        if ($wallet) {
            if ($wallet->trashed()) {
                $wallet->restore();
            }
            $wallet->fill([
                'status' => WalletStatus::Active,
                'updated_by' => $existingUser->id,
            ]);
            $wallet->save();
        } else {
            $existingUser->wallet()->create([
                'balance' => 0,
                'status' => WalletStatus::Active,
                'version' => 0,
                'created_by' => $existingUser->id,
            ]);
        }

        return [
            'user' => $existingUser,
            'token' => $this->createToken($existingUser),
        ];
    }

    private function createNewUser(string $phone, array $data): array
    {
        $user = User::create([
            'phone' => $phone,
            'name' => $data['name'],
            'password' => $data['password'],
            'status' => UserStatus::Active,
        ]);

        $user
            ->wallet()
            ->withTrashed()
            ->firstOrCreate(
                [],
                [
                    'balance' => 0,
                    'status' => WalletStatus::Active,
                    'version' => 0,
                    'created_by' => $user->id,
                ],
            );

        return [
            'user' => $user,
            'token' => $this->createToken($user),
        ];
    }

    private function isRestrictedForReRegistration(User $user, ?\App\Models\Wallet $wallet): bool
    {
        return in_array($user->status, self::RESTRICTED_USER_STATUSES, true) ||
            in_array($wallet?->status, self::RESTRICTED_WALLET_STATUSES, true);
    }

    private function userByPhone(string $phone, bool $withTrashed = false): ?User
    {
        $query = $withTrashed ? User::withTrashed() : User::query();
        $digits = ltrim($phone, '+');

        return $query
            ->whereRaw("REPLACE(phone, '+', '') = ?", [$digits])
            ->first();
    }

    private function createToken(User $user): string
    {
        $scopes = config('auth_api.scopes', ['user:read']);

        return $user->createToken(
            'flutter',
            $scopes,
            now()->addMinutes((int) config('auth_api.access_token_ttl_minutes', 15)),
        )->plainTextToken;
    }
}
