<?php

namespace Tests\Feature;

use App\Models\RelocationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelocationRequestAccountNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_relocation_request_stores_the_authenticated_users_account_number(): void
    {
        $user = User::factory()->create(['broadband_account_number' => 'CG12345678']);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/relocation-requests/create', [
                'broadband_account_number' => $user->broadband_account_number,
                'current_address' => 'Current address',
                'new_address' => 'New address',
                'phone' => '+95912345678',
            ])
            ->assertOk()
            ->assertJsonPath('data.broadband_account_number', 'CG12345678');

        $this->assertDatabaseHas('relocation_requests', [
            'user_id' => $user->id,
            'broadband_account_number' => 'CG12345678',
        ]);
    }

    public function test_relocation_request_rejects_another_users_account_number(): void
    {
        $user = User::factory()->create(['broadband_account_number' => 'CG12345678']);
        User::factory()->create(['broadband_account_number' => 'CG87654321']);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/relocation-requests/create', [
                'broadband_account_number' => 'CG87654321',
                'current_address' => 'Current address',
                'new_address' => 'New address',
                'phone' => '+95912345678',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('broadband_account_number');

        $this->assertSame(0, RelocationRequest::query()->count());
    }
}
