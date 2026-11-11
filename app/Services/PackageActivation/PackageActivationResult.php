<?php

namespace App\Services\PackageActivation;

final class PackageActivationResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $username = null,
        public readonly ?string $password = null,
        public readonly ?string $message = null,
        public readonly ?int $errorCode = null,
        public readonly bool $definitiveFailure = false,
        public readonly ?array $externalResponse = null,
    ) {}

    public static function success(
        string $username,
        string $password,
        ?int $errorCode = 0,
        ?array $externalResponse = null,
    ): self
    {
        return new self(true, $username, $password, errorCode: $errorCode, externalResponse: $externalResponse);
    }

    public static function failed(
        ?string $message = null,
        ?int $errorCode = null,
        ?array $externalResponse = null,
    ): self
    {
        return new self(
            false,
            message: $message,
            errorCode: $errorCode,
            definitiveFailure: true,
            externalResponse: $externalResponse,
        );
    }

    public static function pending(
        ?string $message = null,
        ?int $errorCode = null,
        ?array $externalResponse = null,
    ): self
    {
        return new self(false, message: $message, errorCode: $errorCode, externalResponse: $externalResponse);
    }
}
