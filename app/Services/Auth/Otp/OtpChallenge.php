<?php

namespace App\Services\Auth\Otp;

final readonly class OtpChallenge
{
    public function __construct(public string $providerReference, public ?string $debugCode = null) {}
}
