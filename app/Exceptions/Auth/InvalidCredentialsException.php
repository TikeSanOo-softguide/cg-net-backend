<?php

namespace App\Exceptions\Auth;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Thrown when a verified OTP session fails the password step. OtpService counts
 * these against the verification token so passwords cannot be guessed forever.
 */
final class InvalidCredentialsException extends HttpException
{
    public function __construct()
    {
        parent::__construct(422, 'The provided credentials are incorrect.');
    }
}
