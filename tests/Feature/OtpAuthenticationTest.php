<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OtpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
    }

    public function test_customer_can_authenticate_with_a_phone_otp(): void
    {
        $user = User::factory()->create(['phone' => '95912345678']);

        $challenge = $this->postJson('/api/auth/otp/request', ['phone' => '+95912345678'])
            ->assertAccepted()
            ->json();

        $response = $this->postJson('/api/auth/otp/verify', [
            'challenge_id' => $challenge['challenge_id'],
            'code' => $challenge['debug_otp'],
        ]);

        $response->assertOk()->assertJsonPath('user.id', $user->id);
        $this->withToken($response->json('token'))->getJson('/api/auth/me')->assertOk()->assertJsonPath('id', $user->id);
    }

    public function test_invalid_otp_cannot_authenticate_a_customer(): void
    {
        User::factory()->create(['phone' => '95912345678']);
        $challenge = $this->postJson('/api/auth/otp/request', ['phone' => '+95912345678'])->json();

        $this->postJson('/api/auth/otp/verify', [
            'challenge_id' => $challenge['challenge_id'],
            'code' => '000000',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('otp_challenges', ['challenge_id' => $challenge['challenge_id'], 'failed_attempts' => 1]);
    }

    public function test_sms_provider_failure_does_not_create_a_challenge(): void
    {
        config()->set('otp.provider', 'smspoh');
        config()->set('otp.smspoh.api_key', 'test-key');
        config()->set('otp.smspoh.api_secret', 'test-secret');
        Http::fake([ '*' => Http::response([], 503) ]);

        $this->postJson('/api/auth/otp/request', ['phone' => '+95912345678'])
            ->assertServiceUnavailable();

        $this->assertDatabaseCount('otp_challenges', 0);
    }

    public function test_logout_revokes_only_the_current_device_token(): void
    {
        $user = User::factory()->create(['phone' => '95912345678']);
        $firstToken = $user->createToken('first')->plainTextToken;
        $secondToken = $user->createToken('second')->plainTextToken;

        $this->withToken($firstToken)->postJson('/api/auth/logout')->assertOk();
        $this->withToken($firstToken)->getJson('/api/auth/me')->assertUnauthorized();
        $this->withToken($secondToken)->getJson('/api/auth/me')->assertOk();
    }
}
