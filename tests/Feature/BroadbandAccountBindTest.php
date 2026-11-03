<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BroarbandAccount\AccountInfoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_bind_rejects_an_account_number_already_used_by_another_user(): void
    {
        $user = User::factory()->create();
        User::factory()->create([
            'service_type' => 'CG',
            'broadband_account_number' => '12345678',
        ]);
        $this->mock(AccountInfoService::class)
            ->shouldReceive('find')
            ->once()
            ->with('CG', '12345678')
            ->andReturn(['account' => '12345678', 'status' => 'active']);

        $this->withToken($user->createToken('bind')->plainTextToken)
            ->postJson('/api/broadband-account/bind', [
                'account_number' => '12345678',
                'service_type' => 'CG',
            ])
            ->assertConflict()
            ->assertJsonPath('code', 'MSG-BB-010')
            ->assertJsonPath('message', 'broadband_account_already_bound');

        $this->assertNull($user->fresh()->broadband_account_number);
        $this->assertNull($user->fresh()->service_type);
    }

    public function test_bind_stores_service_type_and_unbinds_the_matching_account(): void
    {
        config([
            'services.cg_api.url' => 'https://provider.test/WalletApp/ServerCommand',
            'services.cg_api.client_id' => 'customer-client',
            'services.cg_api.client_secret' => 'customer-secret',
        ]);
        Http::fake([
            'provider.test/WalletApp/ServerCommand/GetTokenAPI*' => Http::response([
                'ErrCode' => 0,
                'token' => 'customer-test-token',
                'expires_in' => 3600,
            ]),
            'provider.test/WalletApp/ServerCommand/AccountInfoAPI*' => Http::response([
                'ErrCode' => 0,
                'Data' => ['account' => '12345678', 'status' => 'active'],
            ]),
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('bind')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/broadband-account/bind', [
                'account_number' => '12345678',
                'service_type' => 'CG',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('12345678', $user->fresh()->broadband_account_number);
        $this->assertSame('CG', $user->fresh()->service_type);

        $this->withToken($token)
            ->postJson('/api/broadband-account/unbind', ['account_number' => '12345678'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNull($user->fresh()->broadband_account_number);
        $this->assertNull($user->fresh()->service_type);
    }

    public function test_unbind_rejects_an_account_that_does_not_match_the_current_binding(): void
    {
        $user = User::factory()->create([
            'service_type' => 'CG',
            'broadband_account_number' => '12345678',
        ]);

        $this->withToken($user->createToken('unbind')->plainTextToken)
            ->postJson('/api/broadband-account/unbind', ['account_number' => '87654321'])
            ->assertNotFound();

        $this->assertSame('12345678', $user->fresh()->broadband_account_number);
        $this->assertSame('CG', $user->fresh()->service_type);
    }
}
