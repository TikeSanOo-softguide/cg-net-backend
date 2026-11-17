<?php

namespace Tests\Unit;

use App\Services\Auth\Otp\CountryRoutedOtpProvider;
use App\Services\Auth\Otp\OtpChallenge;
use App\Services\Auth\Otp\OtpProviderInterface;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CountryRoutedOtpProviderTest extends TestCase
{
    public function test_country_routed_and_legacy_sms_poh_settings_enable_country_routing(): void
    {
        foreach (['country_routed', 'smspoh'] as $providerName) {
            config(['otp.provider' => $providerName]);
            $this->app->forgetInstance(OtpProviderInterface::class);

            $this->assertInstanceOf(CountryRoutedOtpProvider::class, app(OtpProviderInterface::class));
        }
    }

    public function test_myanmar_uses_sms_poh_for_sending_and_verifying(): void
    {
        $smsPoh = Mockery::mock(OtpProviderInterface::class);
        $twilio = Mockery::mock(OtpProviderInterface::class);
        $smsPoh->shouldReceive('send')
            ->once()
            ->with('959223456789')
            ->andReturn(new OtpChallenge('request-123', '123456'));
        $smsPoh->shouldReceive('verify')
            ->once()
            ->with('request-123', '123456')
            ->andReturn(true);
        $twilio->shouldNotReceive('send');
        $twilio->shouldNotReceive('verify');

        $provider = new CountryRoutedOtpProvider($smsPoh, $twilio);
        $challenge = $provider->send('959223456789');

        $this->assertSame('smspoh:request-123', $challenge->providerReference);
        $this->assertSame('123456', $challenge->debugCode);
        $this->assertTrue($provider->verify($challenge->providerReference, '123456'));
    }

    public function test_china_and_thailand_use_twilio_for_sending_and_verifying(): void
    {
        $smsPoh = Mockery::mock(OtpProviderInterface::class);
        $twilio = Mockery::mock(OtpProviderInterface::class);
        $smsPoh->shouldNotReceive('send');
        $smsPoh->shouldNotReceive('verify');
        $twilio->shouldReceive('send')
            ->once()
            ->with('8613812345678')
            ->andReturn(new OtpChallenge('8613812345678'));
        $twilio->shouldReceive('send')
            ->once()
            ->with('66812345678')
            ->andReturn(new OtpChallenge('66812345678'));
        $twilio->shouldReceive('verify')->once()->with('8613812345678', '123456')->andReturn(true);
        $twilio->shouldReceive('verify')->once()->with('66812345678', '654321')->andReturn(true);

        $provider = new CountryRoutedOtpProvider($smsPoh, $twilio);

        foreach ([
            ['8613812345678', '123456'],
            ['66812345678', '654321'],
        ] as [$phone, $code]) {
            $challenge = $provider->send($phone);

            $this->assertSame('twilio:'.$phone, $challenge->providerReference);
            $this->assertTrue($provider->verify($challenge->providerReference, $code));
        }
    }

    public function test_unsupported_countries_do_not_fall_back_to_a_provider(): void
    {
        $provider = new CountryRoutedOtpProvider(
            Mockery::mock(OtpProviderInterface::class),
            Mockery::mock(OtpProviderInterface::class),
        );

        $this->expectException(RuntimeException::class);
        $provider->send('447911123456');
    }
}
