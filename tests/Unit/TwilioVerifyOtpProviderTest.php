<?php

namespace Tests\Unit;

use App\Services\Auth\Otp\TwilioVerifyOtpProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TwilioVerifyOtpProviderTest extends TestCase
{
    public function test_it_sends_and_verifies_codes_with_twilio_verify(): void
    {
        $this->configureTwilio();
        Http::fake([
            'https://verify.twilio.com/v2/Services/VA123/Verifications' => Http::response([
                'sid' => 'VE123',
                'status' => 'pending',
            ], 201),
            'https://verify.twilio.com/v2/Services/VA123/VerificationCheck' => Http::response([
                'sid' => 'VE123',
                'status' => 'approved',
            ]),
        ]);

        $provider = new TwilioVerifyOtpProvider;
        $challenge = $provider->send('8613812345678');

        $this->assertSame('8613812345678', $challenge->providerReference);
        $this->assertTrue($provider->verify($challenge->providerReference, '012345'));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
                && $request->url() === 'https://verify.twilio.com/v2/Services/VA123/Verifications'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('AC123:auth-token'))
                && $request['To'] === '+8613812345678'
                && $request['Channel'] === 'sms',
        );
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
                && $request->url() === 'https://verify.twilio.com/v2/Services/VA123/VerificationCheck'
                && $request['To'] === '+8613812345678'
                && $request['Code'] === '012345',
        );
    }

    public function test_it_only_accepts_an_approved_twilio_verification(): void
    {
        $this->configureTwilio();
        Http::fake([
            'https://verify.twilio.com/v2/Services/VA123/VerificationCheck' => Http::response([
                'status' => 'pending',
            ]),
        ]);

        $this->assertFalse((new TwilioVerifyOtpProvider)->verify('66812345678', '123456'));
    }

    private function configureTwilio(): void
    {
        config([
            'otp.twilio.account_sid' => 'AC123',
            'otp.twilio.auth_token' => 'auth-token',
            'otp.twilio.verify_service_sid' => 'VA123',
        ]);
    }
}
