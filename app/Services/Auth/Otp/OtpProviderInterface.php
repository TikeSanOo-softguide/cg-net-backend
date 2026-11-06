<?php

namespace App\Services\Auth\Otp;

use App\Services\Auth\Otp\OtpChallenge;
use App\Enums\OtpVerificationResult;

interface OtpProviderInterface
{
    public function send(string $phone): OtpChallenge;

    public function verify(string $providerReference, string $code): OtpVerificationResult;
}
