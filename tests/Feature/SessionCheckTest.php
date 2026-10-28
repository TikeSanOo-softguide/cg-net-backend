<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SessionCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_it_shows_an_alert_when_the_user_has_an_active_session_token(): void
    {
        $user = User::factory()->create(['phone' => '95922345678']);
        $token = $user->createToken('session-check')->accessToken;
        $token->forceFill(['expires_at' => now()->addHour()])->save();

        $this->postJson('/api/auth/session/check', ['phone' => '+95922345678'])
            ->assertOk()
            ->assertExactJson([
                'show_alert' => true,
                'account_status' => 'active',
                'is_active' => true,
            ]);
    }

    public function test_it_does_not_show_an_alert_for_a_device_token_without_an_active_session(): void
    {
        $user = User::factory()->create(['phone' => '95922345678']);
        $user->deviceTokens()->create(['token' => 'registered-device-token']);

        $this->postJson('/api/auth/session/check', ['phone' => '95922345678'])
            ->assertOk()
            ->assertJsonPath('show_alert', false);
    }

    public function test_it_does_not_show_an_alert_for_an_expired_session_token(): void
    {
        $user = User::factory()->create(['phone' => '95922345678']);
        $token = $user->createToken('session-check')->accessToken;
        $token->forceFill(['expires_at' => now()->subSecond()])->save();

        $this->postJson('/api/auth/session/check', ['phone' => '95922345678'])
            ->assertOk()
            ->assertJsonPath('show_alert', false);
    }

    public function test_it_does_not_show_an_alert_without_a_user(): void
    {
        $this->postJson('/api/auth/session/check', ['phone' => '95922345679'])
            ->assertOk()
            ->assertExactJson([
                'show_alert' => false,
                'account_status' => null,
                'is_active' => null,
            ]);
    }

    public function test_it_returns_the_deactivated_account_code(): void
    {
        User::factory()->create([
            'phone' => '95922345678',
            'status' => UserStatus::Deactivated,
        ]);

        $this->postJson('/api/auth/session/check', ['phone' => '95922345678'])
            ->assertForbidden()
            ->assertExactJson([
                'code' => 'MSG-AUTH-020',
                'account_status' => 'deactivated',
                'is_active' => false,
                'show_alert' => false,
            ]);
    }

    public function test_it_returns_the_inactive_account_code_for_a_suspended_account(): void
    {
        User::factory()->create([
            'phone' => '95922345678',
            'status' => UserStatus::Suspended,
        ]);

        $this->postJson('/api/auth/session/check', ['phone' => '95922345678'])
            ->assertForbidden()
            ->assertExactJson([
                'code' => 'MSG-AUTH-020',
                'account_status' => 'suspended',
                'is_active' => false,
                'show_alert' => false,
            ]);
    }

    public function test_it_validates_the_phone_number(): void
    {
        $this->postJson('/api/auth/session/check', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        $this->postJson('/api/auth/session/check', ['phone' => 'not-a-phone'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_it_rate_limits_session_checks(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/auth/session/check', ['phone' => '95922345678'])->assertOk();
        }

        $this->postJson('/api/auth/session/check', ['phone' => '95922345678'])
            ->assertTooManyRequests();
    }
}
