<?php

namespace Tests\Feature;

use App\Models\ChangePlanRequest;
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

    public function test_relocation_request_rejects_a_second_pending_request_for_the_same_broadband_account(): void
    {
        $user = User::factory()->create(['broadband_account_number' => 'CG12345678']);
        RelocationRequest::factory()->create([
            'user_id' => $user->id,
            'broadband_account_number' => $user->broadband_account_number,
            'status' => 'under_review',
        ]);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/relocation-requests/create', [
                'broadband_account_number' => $user->broadband_account_number,
                'current_address' => 'Current address',
                'new_address' => 'New address',
                'phone' => '+95912345678',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('request');

        $this->assertSame(1, RelocationRequest::query()->count());
    }

    public function test_pending_request_of_another_type_does_not_block_relocation_request(): void
    {
        $user = User::factory()->create(['broadband_account_number' => 'CG12345678']);
        ChangePlanRequest::factory()->create([
            'user_id' => $user->id,
            'broadband_account_number' => $user->broadband_account_number,
            'status' => 'under_review',
        ]);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/relocation-requests/create', [
                'broadband_account_number' => $user->broadband_account_number,
                'current_address' => 'Current address',
                'new_address' => 'New address',
                'phone' => '+95912345678',
            ])
            ->assertOk();

        $this->assertSame(1, RelocationRequest::query()->count());
    }

    public function test_pending_request_for_another_broadband_account_does_not_block_relocation_request(): void
    {
        $user = User::factory()->create(['broadband_account_number' => 'CG12345678']);
        RelocationRequest::factory()->create([
            'user_id' => $user->id,
            'broadband_account_number' => 'CG87654321',
            'status' => 'under_review',
        ]);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/relocation-requests/create', [
                'broadband_account_number' => $user->broadband_account_number,
                'current_address' => 'Current address',
                'new_address' => 'New address',
                'phone' => '+95912345678',
            ])
            ->assertOk();

        $this->assertSame(2, RelocationRequest::query()->count());
    }
}
