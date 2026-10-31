<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BroadbandAccountBindTest extends TestCase
{
    use RefreshDatabase;

    public function test_bind_rejects_a_service_type_other_than_ftth_or_cg(): void
    {
        $user = User::factory()->create(['broadband_account_number' => null]);

        $this->withToken($user->createToken('bind')->plainTextToken)
            ->postJson('/api/broadband-account/bind', [
                'account_number' => '12345678',
                'service_type' => 'OTHER',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('service_type');

        $this->assertNull($user->fresh()->broadband_account_number);
    }
}
