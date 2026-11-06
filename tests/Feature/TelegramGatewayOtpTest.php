<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class TelegramGatewayOtpTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '95922345678';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('otp.provider', 'telegram');
        config()->set('otp.cache_store', 'array');
        config()->set('otp.telegram.base_url', 'https://gatewayapi.telegram.org');
        config()->set('otp.telegram.token', 'test-gateway-token');
        config()->set('otp.telegram.code_length', 6);
        config()->set('otp.telegram.timeout', 10);
        Cache::store('array')->flush();
    }

    public function test_send_uses_e164_and_returns_no_code(): void
    {
        Http::fake([
            '*sendVerificationMessage' => Http::response([
                'ok' => true,
                'result' => ['request_id' => 'telegram-request-1'],
            ]),
        ]);

        $response = $this->postJson('/api/auth/otp/request', ['phone' => '+95922345678'])
            ->assertAccepted()
            ->assertJsonMissingPath('debug_otp');

        $request = Http::recorded()->first()[0];
        $this->assertSame('POST', $request->method());
        $this->assertSame('Bearer test-gateway-token', $request->header('Authorization')[0]);
        $this->assertSame('+95922345678', $request->data()['phone_number']);
        $this->assertSame(6, $request->data()['code_length']);
        $this->assertSame(300, $request->data()['ttl']);
        $this->assertArrayNotHasKey('code', $request->data());

        $challenge = Cache::store('array')->get(
            'auth:otp:challenge:' . hash('sha256', $response->json('challenge_id')),
        );
        $this->assertSame('telegram-request-1', $challenge['provider_reference']);
        $this->assertArrayNotHasKey('code', $challenge);
        $this->assertArrayNotHasKey('code_hash', $challenge);
    }

    public function test_number_not_on_telegram_returns_a_safe_error(): void
    {
        Http::fake([
            '*sendVerificationMessage' => Http::response(
                [
                    'ok' => false,
                    'error' => 'PHONE_NUMBER_NOT_REGISTERED',
                ],
                400,
            ),
        ]);

        $this->postJson('/api/auth/otp/request', ['phone' => '+95922345678'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'MSG-AUTH-020')
            ->assertJsonPath('message', 'This number cannot receive Telegram verification codes.');
    }

    public function test_invalid_gateway_number_returns_a_safe_error(): void
    {
        Http::fake([
            '*sendVerificationMessage' => Http::response(
                [
                    'ok' => false,
                    'error' => 'PHONE_NUMBER_INVALID',
                ],
                400,
            ),
        ]);

        $this->postJson('/api/auth/otp/request', ['phone' => '+95922345678'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'MSG-AUTH-021')
            ->assertJsonPath('message', 'This phone number is invalid.');
    }

    public function test_insufficient_gateway_balance_returns_a_safe_error(): void
    {
        Http::fake([
            '*sendVerificationMessage' => Http::response(
                [
                    'ok' => false,
                    'error' => 'BALANCE_TOO_LOW',
                ],
                400,
            ),
        ]);

        $this->postJson('/api/auth/otp/request', ['phone' => '+95922345678'])
            ->assertStatus(503)
            ->assertJsonPath('code', 'MSG-AUTH-024')
            ->assertJsonMissing(['message' => 'BALANCE_TOO_LOW']);
    }

    public function test_timeout_returns_a_safe_error_without_provider_details(): void
    {
        Http::fake(fn() => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->postJson('/api/auth/otp/request', ['phone' => '+95922345678'])
            ->assertStatus(503)
            ->assertJsonPath('code', 'MSG-AUTH-025')
            ->assertJsonMissing(['message' => 'cURL error 28: Operation timed out']);
    }

    public function test_network_error_returns_a_safe_error_and_logs_only_a_masked_number(): void
    {
        Http::fake(fn() => throw new ConnectionException('Connection refused'));
        Log::spy();

        $this->postJson('/api/auth/otp/request', ['phone' => '+95922345678'])
            ->assertStatus(503)
            ->assertJsonPath('code', 'MSG-AUTH-026')
            ->assertJsonMissing(['message' => 'Connection refused']);

        Log::shouldHaveReceived('warning')
            ->withArgs(
                fn(string $message, array $context): bool => $context['phone'] === '+959******78' &&
                    !str_contains(json_encode($context), '95922345678') &&
                    !str_contains(json_encode($context), 'test-gateway-token'),
            )
            ->once();
    }

    public function test_valid_code_issues_existing_verification_token_without_returning_or_logging_code(): void
    {
        Http::fake([
            '*sendVerificationMessage' => Http::response([
                'ok' => true,
                'result' => ['request_id' => 'telegram-request-1'],
            ]),
            '*checkVerificationStatus' => Http::response([
                'ok' => true,
                'result' => ['verification_status' => ['status' => 'code_valid']],
            ]),
        ]);
        Log::spy();

        $challenge = $this->postJson('/api/auth/otp/request', ['phone' => '+95922345678'])
            ->assertAccepted()
            ->assertJsonMissingPath('debug_otp')
            ->json();

        $this->postJson('/api/auth/otp/verify', [
            'challenge_id' => $challenge['challenge_id'],
            'code' => '123456',
        ])
            ->assertOk()
            ->assertJsonPath('next_step', 'register')
            ->assertJsonStructure(['verification_token']);

        Log::shouldNotHaveReceived('warning');
        Http::assertSent(
            fn(Request $request): bool => str_ends_with($request->url(), '/checkVerificationStatus') &&
                $request->data() === ['request_id' => 'telegram-request-1', 'code' => '123456'],
        );
    }

    public function test_invalid_code_is_rejected(): void
    {
        $challenge = $this->challengeWithStatus('code_invalid');

        $this->verify($challenge)->assertUnprocessable()->assertJsonPath('code', 'MSG-AUTH-005');
    }

    public function test_expired_code_is_rejected_and_challenge_removed(): void
    {
        $challenge = $this->challengeWithStatus('expired');

        $this->verify($challenge)->assertUnprocessable()->assertJsonPath('code', 'MSG-AUTH-005');
        $this->assertNull(
            Cache::store('array')->get('auth:otp:challenge:' . hash('sha256', $challenge['challenge_id'])),
        );
    }

    public function test_failed_verification_does_not_extend_the_original_challenge_ttl(): void
    {
        config()->set('otp.challenge_ttl', 30);
        $challenge = $this->challengeWithStatus('code_invalid');

        $this->travel(25)->seconds();
        $this->verify($challenge)->assertUnprocessable();
        $this->travel(6)->seconds();

        $this->assertNull(
            Cache::store('array')->get('auth:otp:challenge:' . hash('sha256', $challenge['challenge_id'])),
        );
    }

    public function test_gateway_max_attempt_status_locks_challenge(): void
    {
        $challenge = $this->challengeWithStatus('code_max_attempts_exceeded');

        $this->verify($challenge)->assertUnprocessable()->assertJsonPath('code', 'MSG-AUTH-005');
        $this->verify($challenge)->assertUnprocessable();
        Http::assertSentCount(2);
    }

    public function test_fifth_local_attempt_is_checked_and_sixth_is_blocked(): void
    {
        config()->set('otp.rate_limits.otp_verify.max_attempts', 10);
        $challenge = $this->challengeWithStatus('code_invalid');

        foreach (range(1, 5) as $_) {
            $this->verify($challenge)->assertUnprocessable();
        }

        $this->verify($challenge)->assertUnprocessable();
        Http::assertSentCount(6);
        $this->assertSame(
            5,
            Cache::store('array')->get('auth:otp:challenge:' . hash('sha256', $challenge['challenge_id']))['attempts'],
        );
    }

    public function test_resend_cooldown_prevents_another_paid_send_then_revokes_old_request(): void
    {
        Http::fake([
            '*sendVerificationMessage' => Http::sequence()
                ->push(['ok' => true, 'result' => ['request_id' => 'telegram-request-1']])
                ->push(['ok' => true, 'result' => ['request_id' => 'telegram-request-2']]),
            '*revokeVerificationMessage' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $this->postJson('/api/auth/otp/request', ['phone' => '+95922345678'])->assertAccepted();
        $this->postJson('/api/auth/otp/request', ['phone' => '+95922345678'])->assertTooManyRequests();
        Http::assertSentCount(1);

        $this->travel(61)->seconds();
        $this->postJson('/api/auth/otp/request', ['phone' => '+95922345678'])->assertAccepted();
        Http::assertSentCount(3);
        Http::assertSent(
            fn(Request $request): bool => str_ends_with($request->url(), '/revokeVerificationMessage') &&
                $request->data() === ['request_id' => 'telegram-request-1'],
        );
    }

    public function test_request_and_verify_rate_limits_do_not_make_extra_gateway_calls(): void
    {
        config()->set('otp.rate_limits.otp_request.max_attempts', 1);
        config()->set('otp.rate_limits.otp_verify.max_attempts', 1);
        Http::fake([
            '*sendVerificationMessage' => Http::response([
                'ok' => true,
                'result' => ['request_id' => 'telegram-request-1'],
            ]),
            '*checkVerificationStatus' => Http::response([
                'ok' => true,
                'result' => ['verification_status' => ['status' => 'code_invalid']],
            ]),
        ]);

        $challenge = $this->postJson('/api/auth/otp/request', ['phone' => '+95922345678'])
            ->assertAccepted()
            ->json();
        RateLimiter::clear('otp:resend:' . hash('sha256', self::PHONE));
        $this->postJson('/api/auth/otp/request', ['phone' => '+95922345678'])->assertTooManyRequests();

        $payload = ['challenge_id' => $challenge['challenge_id'], 'code' => '123456'];
        $this->postJson('/api/auth/otp/verify', $payload)->assertUnprocessable();
        $this->postJson('/api/auth/otp/verify', $payload)->assertTooManyRequests();
        Http::assertSentCount(2);
    }

    /** @return array{challenge_id: string} */
    private function challengeWithStatus(string $status): array
    {
        Http::fake([
            '*sendVerificationMessage' => Http::response([
                'ok' => true,
                'result' => ['request_id' => 'telegram-request-1'],
            ]),
            '*checkVerificationStatus' => Http::response([
                'ok' => true,
                'result' => ['verification_status' => ['status' => $status]],
            ]),
        ]);

        return $this->postJson('/api/auth/otp/request', ['phone' => '+95922345678'])
            ->assertAccepted()
            ->json();
    }

    /** @param array{challenge_id: string} $challenge */
    private function verify(array $challenge): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/auth/otp/verify', [
            'challenge_id' => $challenge['challenge_id'],
            'code' => '123456',
        ]);
    }
}
