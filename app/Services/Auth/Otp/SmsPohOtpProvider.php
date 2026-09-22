<?php

namespace App\Services\Auth\Otp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class SmsPohOtpProvider implements OtpProviderInterface
{
    public function send(string $phone): OtpChallenge
    {
        $accessToken = $this->accessToken();

        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->withQueryParameters([
                    'to' => $phone,
                    'from' => config('otp.smspoh.sender_id'),
                    'brand' => config('otp.smspoh.brand'),
                    'ttl' => config('otp.challenge_ttl'),
                    'pinLength' => 6,
                    'maxInvalidAttempts' => config('otp.max_attempts'),
                    'template' => config('otp.smspoh.template'),
                    'accessToken' => $accessToken,
                ])
                ->post(rtrim((string) config('otp.smspoh.base_url'), '/') . '/request');
        } catch (ConnectionException $exception) {
            throw new RuntimeException('OTP provider is unavailable.', 0, $exception);
        }

        if ($response->failed() || !$response->json('requestId')) {
            Log::warning('SMSPoh OTP request was rejected.', [
                'status' => $response->status(),
                'body' => str($response->body())->limit(1000)->toString(),
            ]);
            throw new RuntimeException('OTP provider rejected the request.');
        }

        return new OtpChallenge((string) $response->json('requestId'));
    }

    public function verify(string $providerReference, string $code): bool
    {
        $accessToken = $this->accessToken();

        try {
            $response = Http::acceptJson()
                ->timeout(10)
                ->withQueryParameters([
                    'requestId' => $providerReference,
                    'code' => $code,
                    'accessToken' => $accessToken,
                ])
                ->post(rtrim((string) config('otp.smspoh.base_url'), '/') . '/verify');
        } catch (ConnectionException $exception) {
            throw new RuntimeException('OTP provider is unavailable.', 0, $exception);
        }

        if (!$response->successful()) {
            Log::warning('SMSPoh OTP verification was rejected.', [
                'status' => $response->status(),
                'body' => str($response->body())->limit(1000)->toString(),
            ]);
        }

        return $response->successful();
    }

    private function accessToken(): string
    {
        $key = (string) config('otp.smspoh.api_key');
        $secret = (string) config('otp.smspoh.api_secret');

        if ($key === '' || $secret === '') {
            throw new RuntimeException('SMSPoh API credentials are not configured.');
        }

        return base64_encode($key . ':' . $secret);
    }
}
