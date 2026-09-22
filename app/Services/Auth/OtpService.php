<?php

namespace App\Services\Auth;

use App\Models\OtpChallenge;
use App\Services\Auth\Otp\OtpProviderInterface;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class OtpService
{
    public function __construct(private readonly OtpProviderInterface $provider) {}

    /** @return array{challenge_id: string, debug_otp: ?string} */
    public function request(string $phone, string $ip, bool $sendProviderOtp = true, string $purpose = 'registration'): array
    {
        $this->ensureCooldown($phone);
        $this->ensureRateLimit('otp:request:' . hash('sha256', $phone . '|' . $ip), 'otp_request');
        $this->ensureRateLimit('otp:request:ip:' . hash('sha256', $ip), 'otp_request_ip');

        $challengeId = bin2hex(random_bytes(32));

        if (!$sendProviderOtp) {
            return [
                'challenge_id' => $challengeId,
                'debug_otp' => null,
            ];
        }

        $challenge = $this->provider->send($phone);

        $expiresAt = now()->addSeconds((int) config('otp.challenge_ttl'));

        OtpChallenge::create([
            'challenge_id' => $challengeId,
            'phone' => $phone,
            'otp_hash' => $challenge->otpHash,
            'provider_reference' => $challenge->providerReference,
            'purpose' => $purpose,
            'expires_at' => $expiresAt,
        ]);

        $this->store()->put(
            $this->challengeKey($challengeId),
            [
                'phone' => $phone,
                'provider_reference' => $challenge->providerReference,
                'purpose' => $purpose,
                'attempts' => 0,
                'locked_until' => null,
            ],
            (int) config('otp.challenge_ttl'),
        );

        return [
            'challenge_id' => $challengeId,
            'debug_otp' => $challenge->debugCode,
        ];
    }

    public function verify(string $challengeId, string $code, string $purpose = 'registration'): string
    {
        $this->ensureRateLimit('otp:verify:' . hash('sha256', $challengeId), 'otp_verify');

        try {
            return Cache::lock($this->lockKey($challengeId), 10)->block(3, function () use (
                $challengeId,
                $code,
                $purpose,
            ): string {
                $key = $this->challengeKey($challengeId);
                $state = $this->store()->get($key);
                if (!is_array($state)) {
                    $this->invalidOtp();
                }

                $challenge = DB::transaction(function () use ($challengeId, $purpose): ?OtpChallenge {
                    $challenge = OtpChallenge::query()
                        ->where('challenge_id', $challengeId)
                        ->where('purpose', $purpose)
                        ->lockForUpdate()
                        ->first();

                    return $challenge;
                });

                if (!$challenge || $challenge->consumed_at || $challenge->expires_at->isPast()) {
                    $this->invalidOtp();
                }

                $lockedUntil = (int) ($state['locked_until'] ?? 0);
                if ($lockedUntil > 0 && $lockedUntil > now()->timestamp) {
                    $this->invalidOtp();
                }

                if (!$this->provider->verify((string) $state['provider_reference'], $code)) {
                    $attempts = ((int) ($state['attempts'] ?? 0)) + 1;
                    $state['attempts'] = $attempts;
                    $state['locked_until'] =
                        $attempts >= (int) config('otp.max_attempts')
                            ? now()->addSeconds((int) config('otp.challenge_ttl'))->timestamp
                            : null;
                    $this->store()->put($key, $state, (int) config('otp.challenge_ttl'));
                    $challenge->increment('failed_attempts');
                    $this->invalidOtp();
                }

                $phone = (string) $state['phone'];
                $consumed = DB::transaction(function () use ($challenge): int {
                    return OtpChallenge::query()
                        ->whereKey($challenge->getKey())
                        ->whereNull('consumed_at')
                        ->update(['consumed_at' => now(), 'updated_at' => now()]);
                });

                if ($consumed !== 1) {
                    $this->invalidOtp();
                }

                $this->store()->forget($key);

                $verificationToken = bin2hex(random_bytes(32));
                $this->store()->put(
                    $this->verificationKey($verificationToken),
                    ['phone' => $phone],
                    (int) config('otp.verification_token_ttl'),
                );

                return $verificationToken;
            });
        } catch (LockTimeoutException $exception) {
            throw new TooManyRequestsHttpException(3, 'Verification is already in progress.', $exception);
        }
    }

    public function purposeFor(string $challengeId): string
    {
        $purpose = OtpChallenge::query()
            ->where('challenge_id', $challengeId)
            ->value('purpose');

        if (!is_string($purpose) || !in_array($purpose, ['login', 'registration'], true)) {
            $this->invalidOtp();
        }

        return $purpose;
    }

    public function consumeVerificationToken(string $token, Closure $callback): mixed
    {
        try {
            return Cache::lock($this->verificationLockKey($token), 10)->block(3, function () use (
                $token,
                $callback,
            ): mixed {
                $key = $this->verificationKey($token);
                $state = $this->store()->get($key);

                if (!is_array($state) || !isset($state['phone'])) {
                    $this->invalidToken();
                }

                $result = $callback((string) $state['phone']);
                $this->store()->forget($key);

                return $result;
            });
        } catch (LockTimeoutException $exception) {
            throw new TooManyRequestsHttpException(3, 'Registration is already in progress.', $exception);
        }
    }

    private function ensureRateLimit(string $key, string $limit): void
    {
        $maxAttempts = (int) config("otp.rate_limits.$limit.max_attempts");
        $decaySeconds = (int) config("otp.rate_limits.$limit.decay_seconds");

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw new TooManyRequestsHttpException(RateLimiter::availableIn($key), 'Too many requests.');
        }

        RateLimiter::hit($key, $decaySeconds);
    }

    private function ensureCooldown(string $phone): void
    {
        $cooldown = (int) config('otp.resend_cooldown');
        if ($cooldown <= 0) {
            return;
        }

        $key = 'otp:resend:' . hash('sha256', $phone);

        if (RateLimiter::tooManyAttempts($key, 1)) {
            throw new TooManyRequestsHttpException(
                RateLimiter::availableIn($key),
                'Please wait before requesting another code.',
            );
        }

        RateLimiter::hit($key, $cooldown);
    }

    private function invalidOtp(): never
    {
        abort(422, 'The OTP is invalid or expired.');
    }

    private function invalidToken(): never
    {
        abort(422, 'The verification token is invalid or expired.');
    }

    private function store()
    {
        return Cache::store(config('otp.cache_store', 'redis'));
    }

    private function challengeKey(string $challengeId): string
    {
        return 'auth:otp:challenge:' . hash('sha256', $challengeId);
    }

    private function lockKey(string $challengeId): string
    {
        return 'auth:otp:challenge:lock:' . hash('sha256', $challengeId);
    }

    private function verificationKey(string $token): string
    {
        return 'auth:otp:verification:' . hash('sha256', $token);
    }

    private function verificationLockKey(string $token): string
    {
        return 'auth:otp:verification:lock:' . hash('sha256', $token);
    }
}
