<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordChangeSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::clear();
    }

    public function test_changing_password_without_a_current_session_revokes_every_session(): void
    {
        $user = User::factory()->create();
        $user->createToken('phone');
        $user->createToken('tablet');
        $user->deviceTokens()->create(['token' => 'device-a', 'platform' => 'android']);

        User::query()->findOrFail($user->id)->forceFill(['password' => '123456'])->save();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('device_tokens', 0);
    }

    public function test_changing_own_password_keeps_the_current_session_and_drops_the_others(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('current');
        $user->createToken('other');
        $user->deviceTokens()->create(['token' => 'device-a', 'platform' => 'android']);

        $user->withAccessToken($current->accessToken);
        $user->forceFill(['password' => '123456'])->save();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $current->accessToken->id]);
        $this->assertDatabaseCount('device_tokens', 1);
    }

    public function test_saving_other_fields_does_not_revoke_sessions(): void
    {
        $user = User::factory()->create();
        $user->createToken('phone');
        $user->deviceTokens()->create(['token' => 'device-a', 'platform' => 'android']);

        User::query()->findOrFail($user->id)->forceFill(['name' => 'Renamed'])->save();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseCount('device_tokens', 1);
    }

    public function test_in_app_password_change_keeps_the_app_signed_in_but_signs_out_other_devices(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);
        $appToken = $user->createToken('app')->plainTextToken;
        $stolenToken = $user->createToken('stolen')->plainTextToken;

        $challenge = $this->withToken($appToken)
            ->postJson('/api/change-password', [
                'current_password' => 'old-password-123',
                'new_password' => '123456',
                'new_password_confirmation' => '123456',
            ])
            ->assertAccepted()
            ->json();

        $this->withToken($appToken)
            ->postJson('/api/change-password/verify-otp', [
                'challenge_id' => $challenge['challenge_id'],
                'code' => $challenge['debug_otp'],
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('123456', $user->fresh()->password));

        app('auth')->forgetGuards();
        $this->withToken($appToken)->getJson('/api/user')->assertOk();

        app('auth')->forgetGuards();
        $this->withToken($stolenToken)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_new_customer_password_must_be_six_digits(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);
        $token = $user->createToken('app')->plainTextToken;

        foreach (['12345', '1234567', '12ab56'] as $password) {
            $this->withToken($token)
                ->postJson('/api/change-password', [
                    'current_password' => 'old-password-123',
                    'new_password' => $password,
                    'new_password_confirmation' => $password,
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('new_password');
        }

        $this->assertTrue(Hash::check('old-password-123', $user->fresh()->password));
    }
}
