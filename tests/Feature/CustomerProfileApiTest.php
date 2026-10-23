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

    public function test_customer_can_switch_and_retrieve_their_saved_language(): void
    {
        $user = User::factory()->create(['lang' => 'en']);
        $token = $user->createToken('language')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/customer/language', ['lang' => 'my'])
            ->assertOk()
            ->assertJsonPath('data.lang', 'my');

        $this->assertSame('my', $user->fresh()->lang);

        $this->withToken($token)->getJson('/api/customer/profile')->assertOk()->assertJsonPath('data.user.lang', 'my');
    }

    public function test_customer_language_switch_requires_authentication_and_supported_language(): void
    {
        $this->patchJson('/api/customer/language', ['lang' => 'zh'])->assertUnauthorized();

        $user = User::factory()->create(['lang' => 'en']);
        $token = $user->createToken('language')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/customer/language', ['lang' => 'fr'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lang');

        $this->assertSame('en', $user->fresh()->lang);
    }

    public function test_notification_preferences_default_to_true_and_are_returned_in_the_profile(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('notification-preferences')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/customer/profile')
            ->assertOk()
            ->assertJsonPath('data.user.announcements_notifications', true)
            ->assertJsonPath('data.user.system_notifications', true)
            ->assertJsonPath('data.user.promotion_notifications', true)
            ->assertJsonPath('data.user.general_notifications', true);
    }

    public function test_customer_can_fetch_all_four_notification_preferences(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('notification-preferences')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/customer/notification-preferences')
            ->assertOk()
            ->assertJsonPath('announcements_enabled', true)
            ->assertJsonPath('system_enabled', true)
            ->assertJsonPath('promotion_enabled', true)
            ->assertJsonPath('general_enabled', true);
    }

    public function test_customer_can_update_notification_preferences_partially(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('notification-preferences')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/customer/notification-preferences', [
                'announcements_enabled' => false,
                'general_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.announcements_notifications', false)
            ->assertJsonPath('data.system_notifications', true)
            ->assertJsonPath('data.promotion_notifications', true)
            ->assertJsonPath('data.general_notifications', false);

        $this->assertFalse($user->fresh()->announcements_enabled);
        $this->assertFalse($user->fresh()->general_enabled);

        $this->withToken($token)
            ->getJson('/api/customer/profile')
            ->assertOk()
            ->assertJsonPath('data.user.announcements_notifications', false)
            ->assertJsonPath('data.user.general_notifications', false);
    }

    public function test_customer_can_update_notification_preferences_with_legacy_keys(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('notification-preferences')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/customer/notification-preferences', ['general_notifications' => false])
            ->assertOk()
            ->assertJsonPath('data.general_notifications', false);

        $this->assertFalse($user->fresh()->general_enabled);
    }

    public function test_notification_preferences_require_authentication_and_boolean_values(): void
    {
        $this->patchJson('/api/customer/notification-preferences', [
            'general_notifications' => false,
        ])->assertUnauthorized();

        $user = User::factory()->create();
        $this->withToken($user->createToken('notification-preferences')->plainTextToken)
            ->patchJson('/api/customer/notification-preferences', ['general_notifications' => 'off'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('general_enabled');

        $this->assertTrue($user->fresh()->general_enabled);
    }

    public function test_customer_can_update_only_their_name_from_the_profile_api(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'phone' => '95912345678',
        ]);
        $token = $user->createToken('profile')->plainTextToken;

        $this->withToken($token)
            ->patchJson('/api/customer/profile', [
                'name' => '  Updated   Name  ',
                'phone' => '95987654321',
                'status' => 'suspended',
            ])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.name', 'Updated Name')
            ->assertJsonPath('data.user.phone', '95912345678')
            ->assertJsonPath('data.user.status', 'active');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'phone' => '95912345678',
            'status' => 'active',
        ]);
    }

    public function test_customer_profile_name_update_requires_a_valid_name(): void
    {
        $user = User::factory()->create(['name' => 'Original Name']);

        $this->withToken($user->createToken('profile')->plainTextToken)
            ->patchJson('/api/customer/profile', ['name' => '  '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Original Name',
        ]);
    }

    public function test_customer_can_get_only_their_profile_balance_broadband_and_package_credentials(): void
    {
        $user = User::factory()->create([
            'phone' => '95912345678',
            'broadband_account_number' => 'CG12345678',
        ]);
        Wallet::factory()->create(['user_id' => $user->id, 'balance' => 12500]);
        $package = Package::factory()->create(['network_id' => Network::factory()->create()->id]);
        CustomerPackage::factory()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'username' => 'cg-user-456',
            'password' => 'another-package-secret',
            'starts_at' => now()->subDays(2),
            'expires_at' => now()->addMonths(2),
        ]);
        $customerPackage = CustomerPackage::factory()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'username' => 'cg-user-123',
            'password' => 'package-secret',
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
        ]);
        CustomerPackage::factory()
            ->expired()
            ->create([
                'user_id' => $user->id,
                'package_id' => $package->id,
            ]);

        Http::fake([
            'provider.test/WalletApp/ServerCommand/GetTokenAPI*' => Http::response([
                'ErrCode' => 0,
                'Message' => 'ok',
                'token' => 'profile-test-token',
            ]),
            'provider.test/WalletApp/ServerCommand/AccountInfoAPI*' => Http::response([
                'ErrCode' => 0,
                'Message' => 'ok',
                'Data' => ['account' => '12345678', 'customer_name' => 'Profile Customer'],
            ]),
        ]);
        config([
            'services.cg_api.url' => 'https://provider.test/WalletApp/ServerCommand',
            'services.cg_api.client_id' => 'profile-client',
            'services.cg_api.client_secret' => 'profile-secret',
        ]);

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
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Pragma', 'no-cache');
        $this->assertArrayNotHasKey('username', $response->json('data.packages.0'));
        $this->assertArrayNotHasKey('password', $response->json('data.packages.0'));
        $response->assertJsonMissingPath('data.user.password');
        $response->assertJsonMissingPath('data.wallet.user');
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

        Http::fake([
            'provider.test/WalletApp/ServerCommand/GetTokenAPI*' => Http::response([
                'ErrCode' => 0,
                'Message' => 'ok',
                'token' => 'profile-test-token',
            ]),
            'provider.test/WalletApp/ServerCommand/AccountInfoAPI*' => Http::response([
                'ErrCode' => 0,
                'Message' => 'ok',
                'Data' => ['account' => '12345678', 'customer_name' => 'Profile Customer'],
            ]),
        ]);
        config([
            'services.cg_api.url' => 'https://provider.test/WalletApp/ServerCommand',
            'services.cg_api.client_id' => 'profile-client',
            'services.cg_api.client_secret' => 'profile-secret',
        ]);

        $response = $this->withToken($user->createToken('profile')->plainTextToken)
            ->getJson('/api/customer/profile')
            ->assertOk()
            ->assertJsonPath('data.packages', []);

        $this->assertStringNotContainsString('foreign-secret', $response->getContent());
        $this->assertStringNotContainsString('foreign-user', $response->getContent());
    }

    public function test_customer_profile_api_is_rate_limited_per_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('profile')->plainTextToken;

        for ($request = 0; $request < 60; $request++) {
            $this->withToken($token)->getJson('/api/customer/profile')->assertOk();
        }

        $this->withToken($token)->getJson('/api/customer/profile')->assertTooManyRequests();
    }
}
