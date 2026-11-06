<?php

namespace App\Services\Auth\Otp;

use App\Enums\OtpVerificationResult;
use App\Support\PhoneNumber;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class TelegramGatewayOtpProvider implements RevocableOtpProviderInterface
{
    public function send(string $phone): OtpChallenge
    {
        try {
            $phone = '+' . PhoneNumber::normalize($phone);
        } catch (InvalidArgumentException) {
            throw new OtpProviderException(OtpProviderException::INVALID_PHONE);
        }

        $ttl = (int) config('otp.challenge_ttl');
        $codeLength = (int) config('otp.telegram.code_length', 6);
        if ($ttl < 30 || $ttl > 3600 || $codeLength < 4 || $codeLength > 8) {
            throw new OtpProviderException(OtpProviderException::UNAVAILABLE);
        }

        $response = $this->post(
            'sendVerificationMessage',
            [
                'phone_number' => $phone,
                'code_length' => $codeLength,
                'ttl' => $ttl,
            ],
            $phone,
        );

        $requestId = $response->json('result.request_id');
        if (!is_string($requestId) || $requestId === '') {
            $this->logFailure('send', OtpProviderException::UNAVAILABLE, $phone);
            throw new OtpProviderException(OtpProviderException::UNAVAILABLE);
        }

        return new OtpChallenge($requestId);
    }

    public function verify(string $providerReference, string $code): OtpVerificationResult
    {
        $response = $this->post(
            'checkVerificationStatus',
            [
                'request_id' => $providerReference,
                'code' => $code,
            ],
            null,
        );

        return match ($response->json('result.verification_status.status')) {
            'code_valid' => OtpVerificationResult::Valid,
            'expired' => OtpVerificationResult::Expired,
            'code_max_attempts_exceeded' => OtpVerificationResult::MaxAttemptsExceeded,
            default => OtpVerificationResult::Invalid,
        };
    }

    public function revoke(string $providerReference): void
    {
        try {
            $this->post('revokeVerificationMessage', ['request_id' => $providerReference], null);
        } catch (OtpProviderException) {
            // Revoke is best-effort; the cached challenge is invalidated regardless.
        }
    }

    /** @param array<string, int|string> $payload */
    private function post(string $method, array $payload, ?string $phone): Response
    {
        $token = (string) config('otp.telegram.token');
        if ($token === '') {
            $this->logFailure($method, OtpProviderException::UNAVAILABLE, $phone);
            throw new OtpProviderException(OtpProviderException::UNAVAILABLE);
        }

        $baseUrl = rtrim((string) config('otp.telegram.base_url'), '/');

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withToken($token)
                ->timeout((int) config('otp.telegram.timeout', 10))
                ->post($baseUrl . '/' . $method, $payload);
        } catch (ConnectionException $exception) {
            $category = preg_match('/timed?\s*out|timeout|cURL error 28/i', $exception->getMessage())
                ? OtpProviderException::TIMEOUT
                : OtpProviderException::NETWORK;
            $this->logFailure($method, $category, $phone);
            throw new OtpProviderException($category);
        }

        if (!$response->successful() || $response->json('ok') !== true) {
            $category = $this->classifyError((string) $response->json('error', ''), $response->status());
            $this->logFailure($method, $category, $phone);
            throw new OtpProviderException($category);
        }

        return $response;
    }

    private function classifyError(string $error, int $status): string
    {
        $error = strtolower(str_replace(['_', '-'], ' ', $error));

        if (
            $status === 429 ||
            str_contains($error, 'too many') ||
            str_contains($error, 'rate limit') ||
            str_contains($error, 'flood')
        ) {
            return OtpProviderException::RATE_LIMITED;
        }

        if (
            str_contains($error, 'balance') &&
            (str_contains($error, 'low') || str_contains($error, 'insufficient') || str_contains($error, 'not enough'))
        ) {
            return OtpProviderException::INSUFFICIENT_BALANCE;
        }

        if (
            str_contains($error, 'phone') &&
            (str_contains($error, 'not found') ||
                str_contains($error, 'not registered') ||
                str_contains($error, 'not on telegram'))
        ) {
            return OtpProviderException::NOT_ON_TELEGRAM;
        }

        if (str_contains($error, 'phone') && (str_contains($error, 'invalid') || str_contains($error, 'malformed'))) {
            return OtpProviderException::INVALID_PHONE;
        }

        return OtpProviderException::UNAVAILABLE;
    }

    private function logFailure(string $operation, string $category, ?string $phone): void
    {
        Log::warning('Telegram Gateway OTP request failed.', [
            'operation' => $operation,
            'category' => $category,
            'phone' => $phone === null ? null : $this->maskPhone($phone),
        ]);
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return '+' . substr($digits, 0, 3) . str_repeat('*', max(0, strlen($digits) - 5)) . substr($digits, -2);
    }
}
