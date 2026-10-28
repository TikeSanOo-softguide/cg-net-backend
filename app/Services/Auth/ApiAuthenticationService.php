<?php

namespace App\Services\Auth;

use App\Enums\MessageCode;
use App\Enums\UserStatus;
use App\Enums\WalletStatus;
use App\Exceptions\ApiMessageException;
use App\Exceptions\Auth\InvalidCredentialsException;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Ledger\LedgerPoster;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Customer authentication.
 * requestOtp -> verifyOtp -> next_step=password -> login
 *                         -> next_step=register -> register
 *                         -> next_step=authenticated (password step disabled)
 * Deactivated accounts receive the password step and are rejected at login
 * until an administrator reactivates them.
 */
final class ApiAuthenticationService
{
    public const STEP_PASSWORD = 'password';
    public const STEP_REGISTER = 'register';
    public const STEP_AUTHENTICATED = 'authenticated';

    private const RESTRICTED_USER_STATUSES = [UserStatus::Suspended];

    private const RESTRICTED_WALLET_STATUSES = [WalletStatus::Suspended, WalletStatus::Frozen, WalletStatus::Inactive];

    public function __construct(
        private readonly OtpService $otp,
        private readonly LedgerPoster $ledger,
        private readonly ApiTokenService $tokens,
    ) {}

    /** @return array{challenge_id: string, debug_otp: ?string} */
    public function requestOtp(string $phone, string $ip): array
    {
        return $this->otp->request($phone, $ip);
    }

    /**
     * @return array{
     *     next_step: string,
     *     account_status?: string,
     *     verification_token?: string,
     *     user?: User,
     *     token?: string,
     *     expires_at?: CarbonInterface
     * }
     */
    public function verifyOtp(string $challengeId, string $code): array
    {
        $verificationToken = $this->otp->verify($challengeId, $code);
        $phone = (string) $this->otp->phoneForVerificationToken($verificationToken);
        $user = $this->findUserByPhone($phone);

        if ($user === null) {
            return ['next_step' => self::STEP_REGISTER, 'verification_token' => $verificationToken];
        }

        if ($user->status === UserStatus::Deactivated) {
            return [
                'next_step' => self::STEP_PASSWORD,
                'account_status' => UserStatus::Deactivated->value,
                'verification_token' => $verificationToken,
            ];
        }

        if (config('auth_api.require_password_on_login', true)) {
            return ['next_step' => self::STEP_PASSWORD, 'verification_token' => $verificationToken];
        }

        // Password step switched off: a verified OTP is enough to sign in.
        $session = $this->otp->consumeVerificationToken(
            $verificationToken,
            fn(string $phone): array => $this->openSession($this->findUserByPhone($phone)),
        );

        return ['next_step' => self::STEP_AUTHENTICATED, ...$session];
    }

    public function verifiedPhone(string $verificationToken): string
    {
        $phone = $this->otp->phoneForVerificationToken($verificationToken);
        if ($phone === null) {
            throw new ApiMessageException(MessageCode::AuthVerificationTokenInvalid);
        }

        return $phone;
    }

    /** @return array{user: User, token: string, expires_at: CarbonInterface} */
    public function login(string $verificationToken, string $password, string $ip): array
    {
        return $this->otp->consumeVerificationToken(
            $verificationToken,
            fn(string $phone): array => $this->loginWithPassword($phone, $password, $ip),
            (int) config('auth_api.max_password_attempts', 5),
        );
    }

    /** @return array{user: User, token: string, expires_at: CarbonInterface} */
    public function completeRegistration(string $token, array $data): array
    {
        return $this->otp->consumeVerificationToken($token, function (string $phone) use ($data): array {
            try {
                return DB::transaction(function () use ($phone, $data): array {
                    $existingUser = User::withTrashed()->where('phone', $phone)->first();

                    if ($existingUser && !$existingUser->trashed()) {
                        throw new ApiMessageException(MessageCode::AuthRegistrationInUse);
                    }

                    if ($existingUser) {
                        return $this->restoreExistingUser($existingUser, $data);
                    }

                    return $this->createNewUser($phone, $data);
                });
            } catch (UniqueConstraintViolationException) {
                throw new ApiMessageException(MessageCode::AuthRegistrationInUse);
            }
        });
    }

    /** @return array{user: User, token: string, expires_at: CarbonInterface} */
    private function loginWithPassword(string $phone, string $password, string $ip): array
    {
        $accountKey = 'auth:login:user:' . hash('sha256', $phone);
        $ipKey = 'auth:login:ip:' . hash('sha256', $ip);
        $accountMaxAttempts = (int) config('otp.rate_limits.login.max_attempts');
        $ipMaxAttempts = (int) config('otp.rate_limits.login_ip.max_attempts', $accountMaxAttempts);
        $decaySeconds = (int) config('otp.rate_limits.login.decay_seconds');
        $ipDecaySeconds = (int) config('otp.rate_limits.login_ip.decay_seconds', $decaySeconds);

        if (
            RateLimiter::tooManyAttempts($accountKey, $accountMaxAttempts) ||
            RateLimiter::tooManyAttempts($ipKey, $ipMaxAttempts)
        ) {
            throw new ApiMessageException(
                MessageCode::AuthLoginTooManyAttempts,
                429,
                [
                    'retry_after' => max(
                        1,
                        RateLimiter::availableIn($accountKey),
                        RateLimiter::availableIn($ipKey),
                    ),
                ],
            );
        }

        RateLimiter::hit($accountKey, $decaySeconds);
        RateLimiter::hit($ipKey, $ipDecaySeconds);

        $session = DB::transaction(function () use ($phone, $password, $accountKey, $ipKey): array {
            $user = User::query()->where('phone', $phone)->lockForUpdate()->first();

            if (!$user || !Hash::check($password, $user->getAuthPassword())) {
                throw new InvalidCredentialsException();
            }

            if ($user->status === UserStatus::Deactivated) {
                throw new ApiMessageException(MessageCode::AuthAccountDeactivated, 403);
            }

            if ($user->status !== UserStatus::Active) {
                throw new InvalidCredentialsException();
            }

            $hasActiveSession = $user->tokens()
                ->where(fn($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->exists();

            if ($hasActiveSession) {
                RateLimiter::clear($accountKey);
                RateLimiter::clear($ipKey);

                throw new ApiMessageException(MessageCode::AuthAlreadySignedIn, 409);
            }

            return $this->openSession($user);
        });

        RateLimiter::clear($accountKey);
        RateLimiter::clear($ipKey);

        return $session;
    }

    private function findUserByPhone(string $phone): ?User
    {
        // $phone is always canonical (PhoneNumber::normalize): no "+", one row per number.
        return User::query()->where('phone', $phone)->first();
    }

    /**
     * Single active session per customer: signing in revokes older tokens.
     *
     * @return array{user: User, token: string, expires_at: CarbonInterface}
     */
    private function openSession(?User $user): array
    {
        if ($user === null || $user->status !== UserStatus::Active) {
            throw new InvalidCredentialsException();
        }

        $user->tokens()->delete();
        $newToken = $this->tokens->issue($user);

        return [
            'user' => $user,
            'token' => $newToken->plainTextToken,
            'expires_at' => $newToken->accessToken->expires_at,
        ];
    }

    private function restoreExistingUser(User $existingUser, array $data): array
    {
        $wallet = $existingUser->wallet()->withTrashed()->first();

        if ($this->isRestrictedForReRegistration($existingUser, $wallet)) {
            throw new ApiMessageException(MessageCode::AuthAccountRestricted);
        }

        $existingUser->restore();
        $existingUser->fill([
            'name' => $data['name'],
            'password' => $data['password'],
            'lang' => $data['lang'],
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
            $wallet = $existingUser->wallet()->create([
                'balance' => 0,
                'status' => WalletStatus::Active,
                'version' => 0,
                'created_by' => $existingUser->id,
            ]);
        }

        $this->ledger->ensureCustomerLiabilityAccount($wallet);

        return $this->openSession($existingUser);
    }

    private function createNewUser(string $phone, array $data): array
    {
        $user = User::create([
            'phone' => $phone,
            'name' => $data['name'],
            'password' => $data['password'],
            'lang' => $data['lang'],
            'status' => UserStatus::Active,
        ]);

        $wallet = $user
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

        if ($wallet->trashed()) {
            $wallet->restore();
        }

        $this->ledger->ensureCustomerLiabilityAccount($wallet);

        return $this->openSession($user);
    }

    private function isRestrictedForReRegistration(User $user, ?Wallet $wallet): bool
    {
        return in_array($user->status, self::RESTRICTED_USER_STATUSES, true) ||
            in_array($wallet?->status, self::RESTRICTED_WALLET_STATUSES, true);
    }
}
