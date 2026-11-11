<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BroarbandAccount\AccountInfoService;
use App\Services\BroarbandAccount\BroadbandApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternetBillCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_returns_account_product_and_outstanding_bill(): void
    {
        $this->mock(AccountInfoService::class)
            ->shouldReceive('find')
            ->once()
            ->with('FTTH', 'FTTH123456789')
            ->andReturn([
                'account' => 'FTTH123456789',
                'productID' => '218',
                'productName' => '10M',
                'productPrice' => 128,
                'productSpeed' => '10M',
                'name' => 'Maung',
                'mobile' => '09123456789',
                'address' => 'Yangon',
            ]);

        $user = User::factory()->create();

        $this->withToken($user->createToken('pay-bill')->plainTextToken)
            ->postJson('/api/internet-bills/check', [
                'account_number' => 'FTTH123456789',
                'service_type' => 'FTTH',
            ])
            ->assertOk()
            ->assertJsonPath('code', 'MSG-FTTH-014')
            ->assertJsonPath('data.account', 'FTTH123456789')
            ->assertJsonPath('data.service_type', 'FTTH')
            ->assertJsonPath('data.product_id', '218')
            ->assertJsonPath('data.product_price', 128)
            ->assertJsonPath('data.outstanding_amount', 128)
            ->assertJsonPath('data.customer_name', 'Maung')
            ->assertJsonPath('data.phone', '09123456789')
            ->assertJsonPath('data.address', 'Yangon');
    }

    public function test_check_returns_not_found_when_the_account_does_not_match(): void
    {
        $this->mock(AccountInfoService::class)
            ->shouldReceive('find')
            ->once()
            ->andReturn(null);

        $user = User::factory()->create();

        $this->withToken($user->createToken('pay-bill')->plainTextToken)
            ->postJson('/api/internet-bills/check', [
                'account_number' => 'FTTH000',
                'service_type' => 'CG',
            ])
            ->assertNotFound()
            ->assertJsonPath('code', 'MSG-FTTH-004');
    }

    public function test_check_returns_billing_failed_when_the_provider_rejects_the_lookup(): void
    {
        $this->mock(AccountInfoService::class)
            ->shouldReceive('find')
            ->once()
            ->andThrow(new BroadbandApiException('broadband_api_account_request_rejected', 'rejected'));

        $user = User::factory()->create();

        $this->withToken($user->createToken('pay-bill')->plainTextToken)
            ->postJson('/api/internet-bills/check', [
                'account_number' => 'FTTH000',
                'service_type' => 'FTTH',
            ])
            ->assertStatus(502)
            ->assertJsonPath('code', 'MSG-FTTH-005');
    }
}
