<?php

namespace App\Services\Auth\Otp;

use RuntimeException;

final class CountryRoutedOtpProvider implements OtpProviderInterface
{
    public function __construct(
        private readonly OtpProviderInterface $smsPoh,
        private readonly OtpProviderInterface $twilio,
    ) {}

    public function send(string $phone): OtpChallenge
    {
        [$providerName, $provider] = $this->providerForPhone($phone);
        $challenge = $provider->send($phone);

        return new OtpChallenge(
            providerReference: $providerName . ':' . $challenge->providerReference,
            debugCode: $challenge->debugCode,
        );
    }

    public function verify(string $providerReference, string $code): bool
    {
        $separator = strpos($providerReference, ':');

        if ($separator === false) {
            return false;
        }

        $providerName = substr($providerReference, 0, $separator);
        $reference = substr($providerReference, $separator + 1);
        $provider = match ($providerName) {
            'smspoh' => $this->smsPoh,
            'twilio' => $this->twilio,
            default => null,
        };

        return $provider?->verify($reference, $code) ?? false;
    }

    /**
     * @return array{string, OtpProviderInterface}
     */
    private function providerForPhone(string $phone): array
    {
        $number = ltrim($phone, '+');

        if (str_starts_with($number, '95')) {
            return ['twilio', $this->twilio];
        }

        if (str_starts_with($number, '86') || str_starts_with($number, '66')) {
            return ['twilio', $this->twilio];
        }

        throw new RuntimeException('No OTP provider is configured for this country.');
    }
}
