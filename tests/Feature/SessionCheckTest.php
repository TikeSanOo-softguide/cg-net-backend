<?php

namespace Tests\Feature;

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

    public function test_it_shows_an_alert_when_the_user_has_a_device_token(): void
    {
        $user = User::factory()->create(['phone' => '95922345678']);
        $user->deviceTokens()->create(['token' => 'registered-device-token']);

        $this->postJson('/api/auth/session/check', ['phone' => '+95922345678'])
            ->assertOk()
            ->assertExactJson(['show_alert' => true]);
    }

    public function test_it_does_not_show_an_alert_without_a_device_token_or_user(): void
    {
        User::factory()->create(['phone' => '95922345678']);

        $this->postJson('/api/auth/session/check', ['phone' => '95922345678'])
            ->assertOk()
            ->assertExactJson(['show_alert' => false]);

        $this->postJson('/api/auth/session/check', ['phone' => '95922345679'])
            ->assertOk()
            ->assertExactJson(['show_alert' => false]);
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
