<?php

namespace App\Exceptions\Auth;

use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/** An OTP request was refused by a limit; carries how long the caller must wait and which limit fired. */
class OtpThrottledException extends TooManyRequestsHttpException
{
    public const REASON_RESEND_COOLDOWN = 'resend_cooldown';

    public const REASON_RATE_LIMITED = 'rate_limited';

    public function __construct(
        public readonly int $retryAfter,
        public readonly string $reason,
        string $message,
    ) {
        parent::__construct(max(1, $retryAfter), $message);
    }
}
