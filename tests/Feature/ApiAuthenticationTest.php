<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\SecurityLog;
use App\Models\User;
use App\Services\Auth\Otp\MockOtpProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '95912345678';

    protected function setUp(): void
    {
        parent::setUp();

        // Clears any Redis rate limiter hits between tests
        if (app()->environment(['local', 'testing'])) {
            Cache::clear();
        }
    }

    // ---------------------------------------------------------------------
    // Flow: new account
    // ---------------------------------------------------------------------

    public function test_new_phone_is_asked_to_register_and_can_register(): void
    {
        $verify = $this->verifyOtpFor('+95912345678');

        $this->assertSame('register', $verify['next_step']);
        $this->assertArrayNotHasKey('token', $verify);

        $response = $this->postJson('/api/auth/register', [
            'verification_token' => $verify['verification_token'],
            'name' => 'New User',
            'password' => '123456',
            'password_confirmation' => '123456',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['token', 'expires_at', 'user' => ['id', 'phone', 'name']]);
        $this->assertDatabaseHas('users', ['phone' => self::PHONE, 'name' => 'New User']);

        $user = User::query()->where('phone', self::PHONE)->firstOrFail();
        $this->assertDatabaseHas('wallets', ['user_id' => $user->id, 'balance' => 0]);
        $this->assertDatabaseHas('ledger_accounts', [
            'wallet_id' => $user->wallet->id,
            'type' => 'liability',
            'code' => 'CUST-' . $user->wallet->id,
        ]);
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $verify = $this->verifyOtpFor('+95912345678');

        $this->postJson('/api/auth/register', [
            'verification_token' => $verify['verification_token'],
            'name' => 'New User',
            'password' => '123456',
            'password_confirmation' => '654321',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertDatabaseMissing('users', ['phone' => self::PHONE]);
    }

    public function test_registration_requires_a_six_digit_numeric_password(): void
    {
        $verify = $this->verifyOtpFor('+95912345678');

        foreach (['12345', '1234567', '12ab56'] as $password) {
            $this->postJson('/api/auth/register', [
                'verification_token' => $verify['verification_token'],
                'name' => 'New User',
                'password' => $password,
                'password_confirmation' => $password,
            ])->assertUnprocessable()->assertJsonValidationErrors('password');
        }

        $this->assertDatabaseMissing('users', ['phone' => self::PHONE]);
    }

    public function test_registration_verification_token_cannot_be_replayed(): void
    {
        $verify = $this->verifyOtpFor('+95912345678');
        $data = [
            'verification_token' => $verify['verification_token'],
            'name' => 'new-user',
            'password' => '123456',
            'password_confirmation' => '123456',
        ];

        $this->postJson('/api/auth/register', $data)->assertCreated();
        $this->postJson('/api/auth/register', $data)->assertUnprocessable();
    }

    public function test_registration_is_rejected_when_the_phone_already_has_an_account(): void
    {
        User::factory()->create(['phone' => self::PHONE]);
        $verify = $this->verifyOtpFor('+95912345678');

        $this->postJson('/api/auth/register', [
            'verification_token' => $verify['verification_token'],
            'name' => 'Takeover',
            'password' => '123456',
            'password_confirmation' => '123456',
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('users', ['name' => 'Takeover']);
    }

    public function test_soft_deleted_user_is_asked_to_register_and_is_reactivated(): void
    {
        $user = User::factory()->create([
            'phone' => self::PHONE,
            'name' => 'Soft Deleted User',
            'password' => '123456',
        ]);
        $wallet = $user->wallet()->create([
            'balance' => 0,
            'status' => \App\Enums\WalletStatus::Active,
            'version' => 0,
            'created_by' => $user->id,
        ]);
        $user->delete();
        $wallet->delete();

        $verify = $this->verifyOtpFor('+95912345678');
        $this->assertSame('register', $verify['next_step']);

        $this->postJson('/api/auth/register', [
            'verification_token' => $verify['verification_token'],
            'name' => 'Soft Deleted User',
            'password' => '123456',
            'password_confirmation' => '123456',
        ])->assertCreated();

        $restoredUser = User::withTrashed()->where('phone', self::PHONE)->firstOrFail();
        $this->assertNull($restoredUser->deleted_at);
        $this->assertNull($restoredUser->wallet()->withTrashed()->firstOrFail()->deleted_at);
    }

    // ---------------------------------------------------------------------
    // Flow: existing account
    // ---------------------------------------------------------------------

    public function test_registered_phone_also_receives_a_code_and_is_asked_for_password(): void
    {
        User::factory()->create(['phone' => self::PHONE, 'password' => '123456']);

        // The request step must not reveal whether the phone is registered.
        $challenge = $this->requestOtp('+95912345678');
        $this->assertArrayHasKey('debug_otp', $challenge);

        $verify = $this->postJson('/api/auth/otp/verify', [
            'challenge_id' => $challenge['challenge_id'],
            'code' => $challenge['debug_otp'],
        ])->assertOk();

        $verify->assertJsonPath('next_step', 'password')->assertJsonStructure(['verification_token']);
        $this->assertArrayNotHasKey('token', $verify->json());
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_existing_user_logs_in_with_password_after_otp(): void
    {
        $user = User::factory()->create(['phone' => self::PHONE, 'password' => '123456']);
        $verify = $this->verifyOtpFor('+95912345678');

        $this->postJson('/api/auth/login', [
            'verification_token' => $verify['verification_token'],
            'password' => '123456',
        ])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['token', 'expires_at']);
    }

    public function test_login_requires_a_six_digit_numeric_password(): void
    {
        User::factory()->create(['phone' => self::PHONE, 'password' => '123456']);
        $verify = $this->verifyOtpFor('+95912345678');

        $this->postJson('/api/auth/login', [
            'verification_token' => $verify['verification_token'],
            'password' => 'password',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->postJson('/api/auth/login', [
            'verification_token' => $verify['verification_token'],
            'password' => '12345',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_login_requires_a_verified_otp_not_just_phone_and_password(): void
    {
        User::factory()->create(['phone' => self::PHONE, 'password' => '123456']);

        $this->postJson('/api/auth/login', [
            'phone' => '+95912345678',
            'password' => '123456',
        ])->assertUnprocessable();

        $this->postJson('/api/auth/login', [
            'verification_token' => str_repeat('a', 64),
            'password' => '123456',
        ])->assertUnprocessable();
    }

    public function test_login_verification_token_cannot_be_reused_after_success(): void
    {
        User::factory()->create(['phone' => self::PHONE, 'password' => '123456']);
        $verify = $this->verifyOtpFor('+95912345678');
        $payload = ['verification_token' => $verify['verification_token'], 'password' => '123456'];

        $this->postJson('/api/auth/login', $payload)->assertOk();
        $this->postJson('/api/auth/login', $payload)->assertUnprocessable();
    }

    public function test_wrong_password_is_rejected_and_token_is_burned_after_max_attempts(): void
    {
        config()->set('auth_api.max_password_attempts', 3);
        User::factory()->create(['phone' => self::PHONE, 'password' => '123456']);
        $verify = $this->verifyOtpFor('+95912345678');

        foreach (range(1, 3) as $_) {
            $this->postJson('/api/auth/login', [
                'verification_token' => $verify['verification_token'],
                'password' => '000000',
            ])->assertUnprocessable();
        }

        // Even the right password no longer works on this OTP session.
        $this->postJson('/api/auth/login', [
            'verification_token' => $verify['verification_token'],
            'password' => '123456',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_a_wrong_password_below_the_limit_still_allows_a_retry(): void
    {
        User::factory()->create(['phone' => self::PHONE, 'password' => '123456']);
        $verify = $this->verifyOtpFor('+95912345678');

        $this->postJson('/api/auth/login', [
            'verification_token' => $verify['verification_token'],
            'password' => '000000',
        ])->assertUnprocessable();

        $this->postJson('/api/auth/login', [
            'verification_token' => $verify['verification_token'],
            'password' => '123456',
        ])->assertOk();
    }

    public function test_login_failures_are_rate_limited_and_logged_once(): void
    {
        config()->set('auth_api.max_password_attempts', 100);
        User::factory()->create(['phone' => self::PHONE, 'password' => '123456']);
        $verify = $this->verifyOtpFor('+95912345678');
        $payload = ['verification_token' => $verify['verification_token'], 'password' => '000000'];

        foreach (range(1, 5) as $_) {
            $this->postJson('/api/auth/login', $payload)->assertUnprocessable();
        }

        $this->assertDatabaseCount('security_logs', 1);
        $this->assertSame(5, SecurityLog::query()->firstOrFail()->metadata['failed_attempts']);

        $this->postJson('/api/auth/login', $payload)->assertTooManyRequests();
        $this->assertDatabaseCount('security_logs', 1);
    }

    public function test_suspended_user_cannot_log_in(): void
    {
        User::factory()->create([
            'phone' => self::PHONE,
            'password' => '123456',
            'status' => UserStatus::Suspended,
        ]);
        $verify = $this->verifyOtpFor('+95912345678');

        $this->postJson('/api/auth/login', [
            'verification_token' => $verify['verification_token'],
            'password' => '123456',
        ])->assertUnprocessable();
    }

    public function test_unregistered_phone_cannot_use_the_login_endpoint(): void
    {
        $verify = $this->verifyOtpFor('+95912345678');

        $this->postJson('/api/auth/login', [
            'verification_token' => $verify['verification_token'],
            'password' => '123456',
        ])->assertUnprocessable();
    }

    public function test_new_login_revokes_previous_sanctum_tokens_for_same_user(): void
    {
        $user = User::factory()->create(['phone' => self::PHONE, 'password' => '123456']);
        $oldToken = $user->createToken('old-device')->plainTextToken;
        $verify = $this->verifyOtpFor('+95912345678');

        $this->postJson('/api/auth/login', [
            'verification_token' => $verify['verification_token'],
            'password' => '123456',
        ])->assertOk();

        $this->withToken($oldToken)->getJson('/api/user')->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // Password step switch
    // ---------------------------------------------------------------------

    public function test_password_step_can_be_switched_off_so_otp_alone_signs_in(): void
    {
        config()->set('auth_api.require_password_on_login', false);
        $user = User::factory()->create(['phone' => self::PHONE, 'password' => '123456']);

        $verify = $this->verifyOtpFor('+95912345678', ['device_token' => 'otp-only-device', 'platform' => 'android']);

        $this->assertSame('authenticated', $verify['next_step']);
        $this->assertSame($user->id, $verify['user']['id']);
        $this->assertNotEmpty($verify['token']);
        $this->assertArrayNotHasKey('verification_token', $verify);
        $this->assertDatabaseHas('device_tokens', ['user_id' => $user->id, 'token' => 'otp-only-device']);

        $this->withToken($verify['token'])->getJson('/api/user')->assertOk();
    }

    public function test_switched_off_password_step_still_sends_new_phones_to_registration(): void
    {
        config()->set('auth_api.require_password_on_login', false);

        $this->assertSame('register', $this->verifyOtpFor('+95912345678')['next_step']);
    }

    public function test_switched_off_password_step_rejects_suspended_users(): void
    {
        config()->set('auth_api.require_password_on_login', false);
        User::factory()->create(['phone' => self::PHONE, 'status' => UserStatus::Suspended]);

        $challenge = $this->requestOtp('+95912345678');

        $this->postJson('/api/auth/otp/verify', [
            'challenge_id' => $challenge['challenge_id'],
            'code' => $challenge['debug_otp'],
        ])->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    // ---------------------------------------------------------------------
    // Sliding-window access token
    // ---------------------------------------------------------------------

    public function test_issued_token_expires_after_the_configured_idle_window(): void
    {
        $response = $this->registerNewUser();

        $this->assertEqualsWithDelta(
            now()->addDays(180)->getTimestamp(),
            PersonalAccessToken::query()->firstOrFail()->expires_at->getTimestamp(),
            5,
        );
        $this->assertEqualsWithDelta(
            now()->addDays(180)->getTimestamp(),
            strtotime($response->json('expires_at')),
            5,
        );
    }

    public function test_activity_extends_the_token_expiry(): void
    {
        $token = $this->registerNewUser()->json('token');
        PersonalAccessToken::query()->update(['expires_at' => now()->addDays(10)]);

        $this->withToken($token)->getJson('/api/user')->assertOk();

        $this->assertEqualsWithDelta(
            now()->addDays(180)->getTimestamp(),
            PersonalAccessToken::query()->firstOrFail()->expires_at->getTimestamp(),
            5,
        );
    }

    public function test_expiry_is_not_rewritten_on_every_request(): void
    {
        $token = $this->registerNewUser()->json('token');
        $almostCurrent = now()->addDays(180)->subMinutes(10)->startOfSecond();
        PersonalAccessToken::query()->update(['expires_at' => $almostCurrent]);

        $this->withToken($token)->getJson('/api/user')->assertOk();

        $this->assertSame(
            $almostCurrent->getTimestamp(),
            PersonalAccessToken::query()->firstOrFail()->expires_at->getTimestamp(),
        );
    }

    public function test_idle_window_length_is_configurable(): void
    {
        config()->set('auth_api.token.idle_ttl_days', 30);
        $token = $this->registerNewUser()->json('token');

        $this->assertEqualsWithDelta(
            now()->addDays(30)->getTimestamp(),
            PersonalAccessToken::query()->firstOrFail()->expires_at->getTimestamp(),
            5,
        );

        // A shorter window also applies to tokens issued under the old value.
        config()->set('auth_api.token.idle_ttl_days', 7);
        $this->withToken($token)->getJson('/api/user')->assertOk();

        $this->assertEqualsWithDelta(
            now()->addDays(7)->getTimestamp(),
            PersonalAccessToken::query()->firstOrFail()->expires_at->getTimestamp(),
            5,
        );
    }

    public function test_token_expires_after_a_full_idle_window_without_activity(): void
    {
        $token = $this->registerNewUser()->json('token');

        $this->travel(181)->days();
        app('auth')->forgetGuards();

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_token_survives_beyond_the_window_as_long_as_the_user_stays_active(): void
    {
        $token = $this->registerNewUser()->json('token');

        $this->travel(100)->days();
        app('auth')->forgetGuards();
        $this->withToken($token)->getJson('/api/user')->assertOk();

        // 200 days after sign-in, but only 100 since the last activity.
        $this->travel(100)->days();
        app('auth')->forgetGuards();
        $this->withToken($token)->getJson('/api/user')->assertOk();
    }

    public function test_logout_revokes_current_sanctum_token(): void
    {
        $user = User::factory()->create(['phone' => self::PHONE, 'password' => '123456']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token, 'Bearer')->postJson('/api/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        app('auth')->forgetGuards();
        $this->withToken($token, 'Bearer')->getJson('/api/user')->assertUnauthorized();
    }

    // ---------------------------------------------------------------------
    // Device tokens
    // ---------------------------------------------------------------------

    public function test_login_registers_device_token_when_provided(): void
    {
        $user = User::factory()->create(['phone' => self::PHONE, 'password' => '123456']);
        $verify = $this->verifyOtpFor('+95912345678');

        $this->postJson('/api/auth/login', [
            'verification_token' => $verify['verification_token'],
            'password' => '123456',
            'device_token' => 'auto-login-device-token',
            'platform' => 'android',
        ])->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'auto-login-device-token',
            'platform' => 'android',
        ]);
    }

    public function test_registration_registers_device_token_when_provided(): void
    {
        $verify = $this->verifyOtpFor('+95912345678');

        $this->postJson('/api/auth/register', [
            'verification_token' => $verify['verification_token'],
            'name' => 'Device User',
            'password' => '123456',
            'password_confirmation' => '123456',
            'device_token' => 'auto-register-device-token',
            'platform' => 'ios',
        ])->assertCreated();

        $user = User::query()->where('phone', self::PHONE)->firstOrFail();

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'auto-register-device-token',
            'platform' => 'ios',
        ]);
    }

    // ---------------------------------------------------------------------
    // OTP hardening
    // ---------------------------------------------------------------------

    public function test_invalid_otp_does_not_verify(): void
    {
        $challenge = $this->requestOtp('+95912345678');

        $this->postJson('/api/auth/otp/verify', [
            'challenge_id' => $challenge['challenge_id'],
            'code' => '000000',
        ])->assertUnprocessable();
    }

    public function test_expired_otp_challenge_cannot_be_verified(): void
    {
        $challenge = $this->requestOtp('+95912345678');
        Cache::forget('auth:otp:challenge:' . hash('sha256', $challenge['challenge_id']));

        $this->postJson('/api/auth/otp/verify', [
            'challenge_id' => $challenge['challenge_id'],
            'code' => $challenge['debug_otp'],
        ])->assertUnprocessable();
    }

    public function test_challenge_is_locked_after_five_invalid_attempts(): void
    {
        $challenge = $this->requestOtp('+95912345678');

        foreach (range(1, 5) as $_) {
            $this->postJson('/api/auth/otp/verify', [
                'challenge_id' => $challenge['challenge_id'],
                'code' => '000000',
            ])->assertUnprocessable();
        }

        // The correct code must now be refused: either the challenge lock (422)
        // or the per-challenge verify rate limit (429) kicks in first.
        $response = $this->postJson('/api/auth/otp/verify', [
            'challenge_id' => $challenge['challenge_id'],
            'code' => $challenge['debug_otp'],
        ]);

        $this->assertContains($response->getStatusCode(), [422, 429]);
        $this->assertArrayNotHasKey('next_step', $response->json());
    }

    public function test_otp_cannot_be_replayed(): void
    {
        $challenge = $this->requestOtp('+95912345678');
        $payload = [
            'challenge_id' => $challenge['challenge_id'],
            'code' => $challenge['debug_otp'],
        ];

        $this->postJson('/api/auth/otp/verify', $payload)->assertOk();
        $this->postJson('/api/auth/otp/verify', $payload)->assertUnprocessable();
    }

    public function test_otp_request_has_resend_cooldown(): void
    {
        $this->requestOtp('+95912345678');
        $this->postJson('/api/auth/otp/request', ['phone' => '+95912345678'])->assertTooManyRequests();
    }

    public function test_otp_response_tells_the_app_how_long_to_disable_resend(): void
    {
        config()->set('otp.resend_cooldown', 45);

        $this->requestOtp('+95912345678');
        $this->assertSame(45, $this->postJson('/api/auth/otp/request', ['phone' => '+95922223333'])->json('resend_after'));

        // A tap inside the cooldown is refused with the remaining wait.
        $this->postJson('/api/auth/otp/request', ['phone' => '+95912345678'])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertJsonPath('reason', 'resend_cooldown')
            ->assertJsonPath('retry_after', 45);
    }

    public function test_cooldown_response_reports_the_seconds_still_left(): void
    {
        config()->set('otp.resend_cooldown', 60);

        $this->requestOtp('+95912345678');
        $this->travel(25)->seconds();

        $response = $this->postJson('/api/auth/otp/request', ['phone' => '+95912345678'])
            ->assertTooManyRequests()
            ->assertJsonPath('reason', 'resend_cooldown');

        $this->assertEqualsWithDelta(35, $response->json('retry_after'), 1);
        $this->assertSame((string) $response->json('retry_after'), $response->headers->get('Retry-After'));
    }

    public function test_rapid_resend_taps_do_not_use_up_the_hourly_allowance(): void
    {
        // One SMS, then four impatient taps inside the cooldown.
        $this->requestOtp('+95912345678');

        foreach (range(1, 4) as $tap) {
            $this->postJson('/api/auth/otp/request', ['phone' => '+95912345678'])->assertTooManyRequests();
        }

        // Only the one SMS counts: after the cooldown the user can resend normally.
        $this->travel(61)->seconds();
        $this->postJson('/api/auth/otp/request', ['phone' => '+95912345678'])->assertAccepted();

        $this->assertDatabaseCount('security_logs', 0);
    }

    public function test_rejected_taps_do_not_restart_the_resend_cooldown(): void
    {
        config()->set('otp.resend_cooldown', 60);

        $this->requestOtp('+95912345678');

        $this->travel(40)->seconds();
        $this->postJson('/api/auth/otp/request', ['phone' => '+95912345678'])->assertTooManyRequests();

        // 61s after the first SMS, regardless of the tap at 40s.
        $this->travel(21)->seconds();
        $this->postJson('/api/auth/otp/request', ['phone' => '+95912345678'])->assertAccepted();
    }

    public function test_hourly_limit_response_carries_retry_after(): void
    {
        foreach (range(1, 3) as $sent) {
            $this->requestOtp('+95912345678');
            RateLimiter::clear('otp:resend:' . hash('sha256', self::PHONE));
        }

        $this->postJson('/api/auth/otp/request', ['phone' => '+95912345678'])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertJsonPath('reason', 'hourly_limit')
            ->assertJsonStructure(['message', 'retry_after']);
    }

    public function test_security_log_is_created_when_phone_requests_otp_more_than_three_times(): void
    {
        $user = User::factory()->create(['phone' => self::PHONE]);

        foreach (range(1, 4) as $attempt) {
            $response = $this->postJson('/api/auth/otp/request', ['phone' => '+95912345678']);

            if ($attempt < 4) {
                $response->assertAccepted();
                RateLimiter::clear('otp:resend:' . hash('sha256', $user->phone));
            } else {
                $response->assertTooManyRequests();
            }
        }

        $this->assertDatabaseHas('security_logs', [
            'actor_type' => User::class,
            'actor_id' => $user->id,
            'event' => 'otp_request_limit_exceeded',
        ]);
        $this->assertDatabaseCount('security_logs', 1);
    }

    public function test_old_registration_and_credential_login_endpoints_are_gone(): void
    {
        // Unknown POST paths answer 404 or 405 (the web fallback route is GET-only); never a success.
        foreach (['request-otp', 'verify-otp', 'complete'] as $path) {
            $status = $this->postJson('/api/auth/register/' . $path, ['phone' => '+95912345678'])->getStatusCode();

            $this->assertContains($status, [404, 405]);
        }
    }

    public function test_mock_provider_does_not_expose_code_in_production(): void
    {
        config()->set('otp.mock.expose_code', true);
        app()->detectEnvironment(fn() => 'production');

        $provider = new MockOtpProvider();
        $challenge = $provider->send('+95912345678');

        $this->assertNull($challenge->debugCode);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /** @return array{challenge_id: string, debug_otp: string} */
    private function requestOtp(string $phone): array
    {
        return $this->postJson('/api/auth/otp/request', ['phone' => $phone])
            ->assertAccepted()
            ->json();
    }

    /** Runs request + verify and returns the verify response body. */
    private function verifyOtpFor(string $phone, array $extra = []): array
    {
        $challenge = $this->requestOtp($phone);

        return $this->postJson('/api/auth/otp/verify', [
            'challenge_id' => $challenge['challenge_id'],
            'code' => $challenge['debug_otp'],
            ...$extra,
        ])
            ->assertOk()
            ->json();
    }

    private function registerNewUser(): \Illuminate\Testing\TestResponse
    {
        $verify = $this->verifyOtpFor('+95912345678');

        return $this->postJson('/api/auth/register', [
            'verification_token' => $verify['verification_token'],
            'name' => 'New User',
            'password' => '123456',
            'password_confirmation' => '123456',
        ])->assertCreated();
    }
}
