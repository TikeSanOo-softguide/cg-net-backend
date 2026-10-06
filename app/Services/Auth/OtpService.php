<?php

namespace App\Services\Auth;

use App\Exceptions\Auth\InvalidCredentialsException;
use App\Exceptions\Auth\OtpThrottledException;
use App\Services\Auth\Otp\OtpProviderInterface;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class OtpService
{
    public function __construct(private readonly OtpProviderInterface $provider) {}

    /** @return array{challenge_id: string, debug_otp: ?string} */
    public function request(string $phone, string $ip, bool $sendProviderOtp = true): array
    {
        // Check every limit first and spend budget only once the request is accepted.
        // A rejected tap (cooldown, limit) must not use up the hourly allowance or
        // restart the cooldown, otherwise impatient taps on "Resend" lock the user
        // out after a single SMS.
        $cooldownKey = 'otp:resend:' . hash('sha256', $phone);
        $phoneIpKey = 'otp:request:' . hash('sha256', $phone . '|' . $ip);
        $ipKey = 'otp:request:ip:' . hash('sha256', $ip);

        $this->assertWithinLimit(
            $cooldownKey,
            1,
            'Please wait before requesting another code.',
            OtpThrottledException::REASON_RESEND_COOLDOWN,
        );
        $this->assertWithinLimit($phoneIpKey, $this->maxAttempts('otp_request'));
        $this->assertWithinLimit($ipKey, $this->maxAttempts('otp_request_ip'));

        RateLimiter::hit($cooldownKey, (int) config('otp.resend_cooldown'));
        RateLimiter::hit($phoneIpKey, $this->decaySeconds('otp_request'));
        RateLimiter::hit($ipKey, $this->decaySeconds('otp_request_ip'));

        $challengeId = bin2hex(random_bytes(32));

        if (!$sendProviderOtp) {
            return [
                'challenge_id' => $challengeId,
                'debug_otp' => null,
            ];
        }

        $challenge = $this->provider->send($phone);

        $this->store()->put(
            $this->challengeKey($challengeId),
            [
                'phone' => $phone,
                'provider_reference' => $challenge->providerReference,
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

    public function verify(string $challengeId, string $code): string
    {
        $this->ensureRateLimit('otp:verify:' . hash('sha256', $challengeId), 'otp_verify');

        try {
            return Cache::lock($this->lockKey($challengeId), 10)->block(3, function () use (
                $challengeId,
                $code,
            ): string {
                $key = $this->challengeKey($challengeId);
                $state = $this->store()->get($key);
                if (!is_array($state)) {
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
                    $this->invalidOtp();
                }

                $phone = (string) $state['phone'];
                $this->store()->forget($key);

                $verificationToken = bin2hex(random_bytes(32));
                $verificationTtl = (int) config('otp.verification_token_ttl');
                $this->store()->put(
                    $this->verificationKey($verificationToken),
                    [
                        'phone' => $phone,
                        'failures' => 0,
                        'expires_at' => now()->addSeconds($verificationTtl)->timestamp,
                    ],
                    $verificationTtl,
                );

                return $verificationToken;
            });
        } catch (LockTimeoutException $exception) {
            throw new TooManyRequestsHttpException(3, 'Verification is already in progress.', $exception);
        }
    }

    /** Phone a still-valid verification token belongs to, without consuming it. */
    public function phoneForVerificationToken(string $token): ?string
    {
        $state = $this->store()->get($this->verificationKey($token));

        return is_array($state) && isset($state['phone']) ? (string) $state['phone'] : null;
    }

    /**
     * Runs $callback with the verified phone and burns the token on success.
     *
     * If $callback throws InvalidCredentialsException the token is kept so the
     * user can retry, but only $maxFailures times; after that it is burned and a
     * fresh OTP is required. Any other exception leaves the token untouched.
     */
    public function consumeVerificationToken(string $token, Closure $callback, ?int $maxFailures = null): mixed
    {
        try {
            return Cache::lock($this->verificationLockKey($token), 10)->block(3, function () use (
                $token,
                $callback,
                $maxFailures,
            ): mixed {
                $key = $this->verificationKey($token);
                $state = $this->store()->get($key);

                if (!is_array($state) || !isset($state['phone'])) {
                    $this->invalidToken();
                }

                try {
                    $result = $callback((string) $state['phone']);
                } catch (InvalidCredentialsException $exception) {
                    if ($maxFailures !== null) {
                        $this->recordVerificationFailure($key, $state, $maxFailures);
                    }

                    throw $exception;
                }

                $this->store()->forget($key);

                return $result;
            });
        } catch (LockTimeoutException $exception) {
            throw new TooManyRequestsHttpException(3, 'Registration is already in progress.', $exception);
        }
    }

    private function recordVerificationFailure(string $key, array $state, int $maxFailures): void
    {
        $failures = ((int) ($state['failures'] ?? 0)) + 1;

        if ($failures >= $maxFailures) {
            $this->store()->forget($key);

            return;
        }

        $state['failures'] = $failures;
        $remaining = isset($state['expires_at'])
            ? max(1, (int) $state['expires_at'] - now()->timestamp)
            : (int) config('otp.verification_token_ttl');

        $this->store()->put($key, $state, $remaining);
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

    /** Throws 429 (with Retry-After) when $key is exhausted. Does not count the call. */
    private function assertWithinLimit(
        string $key,
        int $maxAttempts,
        string $message = 'Too many OTP requests. Please try again later.',
        string $reason = OtpThrottledException::REASON_RATE_LIMITED,
    ): void {
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw new OtpThrottledException(RateLimiter::availableIn($key), $reason, $message);
        }
    }

    private function maxAttempts(string $limit): int
    {
        return (int) config("otp.rate_limits.$limit.max_attempts");
    }

    private function decaySeconds(string $limit): int
    {
        return (int) config("otp.rate_limits.$limit.decay_seconds");
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
