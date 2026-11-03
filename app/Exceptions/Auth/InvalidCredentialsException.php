<?php

namespace App\Exceptions\Auth;

use App\Enums\MessageCode;
use App\Exceptions\ApiMessageException;

/**
 * Thrown when a verified OTP session fails the password step. OtpService counts
 * these against the verification token so passwords cannot be guessed forever.
 */
final class InvalidCredentialsException extends ApiMessageException
{
    public function __construct()
    {
        parent::__construct(MessageCode::AuthIncorrectPassword, 422);
    }
}
