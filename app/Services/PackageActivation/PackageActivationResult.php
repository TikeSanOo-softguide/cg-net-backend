<?php

namespace App\Services\PackageActivation;

final class PackageActivationResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $username = null,
        public readonly ?string $password = null,
        public readonly ?string $message = null,
    ) {}

    public static function success(string $username, string $password): self
    {
        return new self(true, $username, $password);
    }

    public static function failed(?string $message = null): self
    {
        return new self(false, message: $message);
    }
}
