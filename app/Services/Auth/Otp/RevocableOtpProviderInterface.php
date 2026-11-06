<?php

namespace App\Services\Auth\Otp;

interface RevocableOtpProviderInterface extends OtpProviderInterface
{
    public function revoke(string $providerReference): void;
}
