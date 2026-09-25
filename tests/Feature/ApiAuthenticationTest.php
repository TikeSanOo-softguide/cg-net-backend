<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\Otp\MockOtpProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clears any Redis rate limiter hits between tests
        if (app()->environment(['local', 'testing'])) {
            Cache::clear();
        }
    }

    public function test_user_can_register_after_otp_verification(): void
    {
        $challenge = $this->requestOtp('+95912345678');

        $verification = $this->postJson('/api/auth/register/verify-otp', [
            'challenge_id' => $challenge['challenge_id'],
            'code' => $challenge['debug_otp'],
        ])
            ->assertOk()
            ->json('verification_token');

        $response = $this->postJson('/api/auth/register/complete', [
            'verification_token' => $verification,
            'name' => 'New User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()->assertJsonStructure(['token', 'user' => ['id', 'phone', 'name']]);
        $this->assertDatabaseHas('users', ['phone' => '95912345678', 'name' => 'New User']);
    }

    public function test_invalid_otp_does_not_verify(): void
    {
        $challenge = $this->requestOtp('+95912345678');

        $this->postJson('/api/auth/register/verify-otp', [
            'challenge_id' => $challenge['challenge_id'],
            'code' => '000000',
        ])->assertUnprocessable();
    }

    public function test_expired_otp_challenge_cannot_be_verified(): void
    {
        $challenge = $this->requestOtp('+95912345678');
        Cache::forget('auth:otp:challenge:' . hash('sha256', $challenge['challenge_id']));

        $this->postJson('/api/auth/register/verify-otp', [
            'challenge_id' => $challenge['challenge_id'],
            'code' => $challenge['debug_otp'],
        ])->assertUnprocessable();
    }

    public function test_challenge_is_locked_after_five_invalid_attempts(): void
    {
        $challenge = $this->requestOtp('+95912345678');

        foreach (range(1, 5) as $_) {
            $this->postJson('/api/auth/register/verify-otp', [
                'challenge_id' => $challenge['challenge_id'],
                'code' => '000000',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/auth/register/verify-otp', [
            'challenge_id' => $challenge['challenge_id'],
            'code' => $challenge['debug_otp'],
        ])->assertUnprocessable();
    }

    public function test_otp_cannot_be_replayed(): void
    {
        $challenge = $this->requestOtp('+95912345678');
        $payload = [
            'challenge_id' => $challenge['challenge_id'],
            'code' => $challenge['debug_otp'],
        ];

        $this->postJson('/api/auth/register/verify-otp', $payload)->assertOk();
        $this->postJson('/api/auth/register/verify-otp', $payload)->assertUnprocessable();
    }

    public function test_otp_request_has_resend_cooldown(): void
    {
        $this->requestOtp('+95912345678');
        $this->postJson('/api/auth/register/request-otp', ['phone' => '+95912345678'])->assertTooManyRequests();
    }

    public function test_verification_token_cannot_be_replayed(): void
    {
        $challenge = $this->requestOtp('+95912345678');
        $token = $this->postJson('/api/auth/register/verify-otp', [
            'challenge_id' => $challenge['challenge_id'],
            'code' => $challenge['debug_otp'],
        ])->json('verification_token');

        $data = [
            'verification_token' => $token,
            'name' => 'new-user',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $this->postJson('/api/auth/register/complete', $data)->assertCreated();
        $this->postJson('/api/auth/register/complete', $data)->assertUnprocessable();
    }

    public function test_registered_phone_gets_a_non_verifiable_registration_challenge(): void
    {
        User::factory()->create(['phone' => '95912345678', 'name' => 'Taken User']);

        $this->postJson('/api/auth/register/request-otp', ['phone' => '+95912345678'])
            ->assertAccepted()
            ->assertJsonStructure(['challenge_id'])
            ->assertJsonMissing(['debug_otp']);
    }

    public function test_soft_deleted_user_can_be_reactivated_during_registration(): void
    {
        $user = User::factory()->create([
            'phone' => '95912345678',
            'name' => 'Soft Deleted User',
            'password' => 'password123',
        ]);
        $wallet = $user->wallet()->create([
            'balance' => 0,
            'status' => \App\Enums\WalletStatus::Active,
            'version' => 0,
            'created_by' => $user->id,
        ]);
        $user->delete();
        $wallet->delete();

        $challenge = $this->requestOtp('+95912345678');
        $verification = $this->postJson('/api/auth/register/verify-otp', [
            'challenge_id' => $challenge['challenge_id'],
            'code' => $challenge['debug_otp'],
        ])->json('verification_token');

        $response = $this->postJson('/api/auth/register/complete', [
            'verification_token' => $verification,
            'name' => 'Soft Deleted User',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', ['phone' => '95912345678', 'name' => 'Soft Deleted User']);

        $restoredUser = User::withTrashed()->where('phone', '95912345678')->firstOrFail();
        $this->assertNotNull($restoredUser);
        $this->assertNull($restoredUser->deleted_at);

        $restoredWallet = $restoredUser->wallet()->withTrashed()->firstOrFail();
        $this->assertNull($restoredWallet->deleted_at);
    }

    public function test_user_can_log_in_and_rate_limited_credentials_are_rejected(): void
    {
        $user = User::factory()->create(['phone' => '95912345678', 'password' => 'password123']);

        $this->postJson('/api/auth/login', [
            'phone' => '+95912345678',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        foreach (range(1, 5) as $_) {
            $this->postJson('/api/auth/login', [
                'phone' => '+95912345678',
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', [
            'phone' => '+95912345678',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_mock_provider_does_not_expose_code_in_production(): void
    {
        config()->set('otp.mock.expose_code', true);
        app()->detectEnvironment(fn() => 'production');

        $provider = new MockOtpProvider();
        $challenge = $provider->send('+95912345678');

        $this->assertNull($challenge->debugCode);
    }

    public function test_new_login_revokes_previous_sanctum_tokens_for_same_user(): void
    {
        $user = User::factory()->create(['phone' => '95912345678', 'password' => 'password123']);
        $oldToken = $user->createToken('old-device')->plainTextToken;

        $loginResponse = $this->postJson('/api/auth/login', [
            'phone' => '+95912345678',
            'password' => 'password123',
        ]);

        $loginResponse->assertOk()->assertJsonPath('user.id', $user->id);
        $this->withToken($oldToken)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_logout_revokes_current_sanctum_token(): void
    {
        $user = User::factory()->create(['phone' => '95912345678', 'password' => 'password123']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token, 'Bearer')->postJson('/api/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        app('auth')->forgetUser();
        $this->withToken($token, 'Bearer')->getJson('/api/user')->assertUnauthorized();
    }

    /** @return array{challenge_id: string, debug_otp: string} */
    private function requestOtp(string $phone): array
    {
        return $this->postJson('/api/auth/register/request-otp', ['phone' => $phone])
            ->assertAccepted()
            ->json();
    }
}
