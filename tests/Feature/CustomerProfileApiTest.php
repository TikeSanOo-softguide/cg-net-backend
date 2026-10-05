<?php

namespace Tests\Feature;

use App\Models\CustomerPackage;
use App\Models\Network;
use App\Models\Package;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CustomerProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_profile_api_requires_authentication(): void
    {
        $this->getJson('/api/customer/profile')->assertUnauthorized();
    }

    public function test_customer_can_get_only_their_profile_balance_broadband_and_package_credentials(): void
    {
        $user = User::factory()->create([
            'phone' => '95912345678',
            'broadband_account_number' => 'CG12345678',
        ]);
        Wallet::factory()->create(['user_id' => $user->id, 'balance' => 12500]);
        $package = Package::factory()->create(['network_id' => Network::factory()->create()->id]);
        $customerPackage = CustomerPackage::factory()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'username' => 'cg-user-123',
            'password' => 'package-secret',
            'starts_at' => now()->subDays(2),
            'expires_at' => now()->addMonth(),
        ]);
        CustomerPackage::factory()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'username' => 'cg-user-456',
            'password' => 'another-package-secret',
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonths(2),
        ]);
        CustomerPackage::factory()->expired()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
        ]);

        Http::fake();

        $response = $this->withToken($user->createToken('profile')->plainTextToken)
            ->getJson('/api/customer/profile')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.name', $user->name)
            ->assertJsonPath('data.user.phone', '95912345678')
            ->assertJsonPath('data.user.broadband_account_number', 'CG12345678')
            ->assertJsonPath('data.wallet.balance', 12500)
            ->assertJsonCount(2, 'data.packages')
            ->assertJsonPath('data.packages.0.id', $customerPackage->id)
            ->assertJsonPath('data.packages.0.details.network_name.en', $package->network->name_en)
            ->assertJsonPath('data.packages.0.details.network_name.zh', $package->network->name_zh)
            ->assertJsonPath('data.packages.0.details.network_name.my', $package->network->name_my)
            ->assertJsonPath('data.packages.0.details.speed.mbps', $package->speed->mbps)
            ->assertJsonPath('data.packages.0.details.term.months', $package->term->months)
            ->assertJsonPath('data.packages.0.username', 'cg-user-123')
            ->assertJsonPath('data.packages.0.password', 'package-secret')
            ->assertHeader('Cache-Control', 'private, no-store')
            ->assertHeader('Pragma', 'no-cache');
        $response->assertJsonMissingPath('data.user.password');
        $response->assertJsonMissingPath('data.wallet.user');
        Http::assertNothingSent();
    }

    public function test_customer_profile_does_not_return_another_customers_package_credentials(): void
    {
        $user = User::factory()->create(['broadband_account_number' => 'CG12345678']);
        $otherUser = User::factory()->create();
        $otherPackage = Package::factory()->create(['network_id' => Network::factory()->create()->id]);
        $foreignCustomerPackage = CustomerPackage::factory()->create([
            'user_id' => $otherUser->id,
            'package_id' => $otherPackage->id,
            'username' => 'foreign-user',
            'password' => 'foreign-secret',
            'expires_at' => now()->addMonth(),
        ]);

        Http::fake();

        $response = $this->withToken($user->createToken('profile')->plainTextToken)
            ->getJson('/api/customer/profile')
            ->assertOk()
            ->assertJsonPath('data.packages', []);

        $this->assertStringNotContainsString('foreign-secret', $response->getContent());
        $this->assertStringNotContainsString('foreign-user', $response->getContent());
        Http::assertNothingSent();
    }

    public function test_customer_profile_api_is_rate_limited_per_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('profile')->plainTextToken;

        for ($request = 0; $request < 10; $request++) {
            $this->withToken($token)->getJson('/api/customer/profile')->assertOk();
        }

        $this->withToken($token)->getJson('/api/customer/profile')->assertTooManyRequests();
    }
}
