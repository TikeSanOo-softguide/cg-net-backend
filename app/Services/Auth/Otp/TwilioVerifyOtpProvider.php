<?php

namespace App\Services\Auth\Otp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class TwilioVerifyOtpProvider implements OtpProviderInterface
{
    public function send(string $phone): OtpChallenge
    {
        $response = $this->post('Verifications', [
            'To' => $this->e164Phone($phone),
            'Channel' => 'sms',
        ]);
        $sid = $response->json('sid');

        if (! $response->successful() || ! is_string($sid) || $sid === '') {
            throw new RuntimeException(sprintf('Twilio rejected the OTP request (HTTP %d).', $response->status()));
        }

        return new OtpChallenge($phone);
    }

    public function verify(string $providerReference, string $code): bool
    {
        $response = $this->post('VerificationCheck', [
            'To' => $this->e164Phone($providerReference),
            'Code' => $code,
        ]);

        return $response->successful() && $response->json('status') === 'approved';
    }

    private function post(string $endpoint, array $data): Response
    {
        $accountSid = (string) config('otp.twilio.account_sid');
        $authToken = (string) config('otp.twilio.auth_token');
        $serviceSid = (string) config('otp.twilio.verify_service_sid');

        if ($accountSid === '' || $authToken === '' || $serviceSid === '') {
            throw new RuntimeException('Twilio Verify credentials are not configured.');
        }

        $url = sprintf(
            'https://verify.twilio.com/v2/Services/%s/%s',
            rawurlencode($serviceSid),
            $endpoint,
        );

        try {
            return Http::acceptJson()
                ->asForm()
                ->withBasicAuth($accountSid, $authToken)
                ->timeout(10)
                ->post($url, $data);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Twilio OTP provider is unavailable.', 0, $exception);
        }
    }

    private function e164Phone(string $phone): string
    {
        return str_starts_with($phone, '+') ? $phone : '+'.$phone;
    }
}
