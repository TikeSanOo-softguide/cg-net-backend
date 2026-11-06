<?php

namespace App\Services\Auth\Otp;

use RuntimeException;

final class OtpProviderException extends RuntimeException
{
    public const NOT_ON_TELEGRAM = 'not_on_telegram';
    public const INVALID_PHONE = 'invalid_phone';
    public const INSUFFICIENT_BALANCE = 'insufficient_balance';
    public const RATE_LIMITED = 'rate_limited';
    public const TIMEOUT = 'timeout';
    public const NETWORK = 'network';
    public const UNAVAILABLE = 'unavailable';

    public function __construct(public readonly string $category)
    {
        parent::__construct('OTP provider request failed.');
    }
}