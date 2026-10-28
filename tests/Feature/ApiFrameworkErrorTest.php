<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiFrameworkErrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_validation_errors_include_code_and_level(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('profile')->plainTextToken)
            ->patchJson('/api/customer/profile', [])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'MSG-SYS-002')
            ->assertJsonPath('level', 'error')
            ->assertJsonValidationErrors('name');
    }

    public function test_missing_api_route_includes_not_found_code(): void
    {
        $this->getJson('/api/does-not-exist')
            ->assertNotFound()
            ->assertJsonPath('code', 'MSG-SYS-004')
            ->assertJsonPath('level', 'error');
    }

    public function test_aborted_api_not_found_includes_code_and_level(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('unbind')->plainTextToken)
            ->postJson('/api/broadband-account/unbind')
            ->assertNotFound()
            ->assertJsonPath('code', 'MSG-SYS-004')
            ->assertJsonPath('level', 'error')
            ->assertJsonPath('message', 'The requested resource was not found.');
    }

    public function test_unauthenticated_api_response_keeps_session_code(): void
    {
        $this->getJson('/api/customer/profile')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'MSG-AUTH-017')
            ->assertJsonPath('level', 'error');
    }
}
