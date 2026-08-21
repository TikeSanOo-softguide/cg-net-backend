<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Admin;
use App\Models\BroadbandAccount;
use App\Models\CustomerPackage;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_customers(): void
    {
        $this->get('/customers')->assertRedirect('/login');
    }

    public function test_admins_can_view_paginated_searchable_customer_index(): void
    {
        $admin = Admin::factory()->create();
        $match = User::factory()->create([
            'name' => 'Aung Aung',
            'phone' => '09111111111',
            'nrc_number' => '12/ABC(N)111111',
            'status' => UserStatus::Active,
        ]);
        User::factory()->suspended()->create([
            'name' => 'Hidden User',
            'phone' => '09222222222',
        ]);

        $this->actingAs($admin, 'web')
            ->get('/customers?search=Aung&sort=name&direction=asc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Customer/Index')
                ->where('filters.search', 'Aung')
                ->where('filters.sort', 'name')
                ->has('customers.data', 1)
                ->where('customers.data.0.id', $match->id)
                ->where('customers.data.0.name', 'Aung Aung'));

        $this->actingAs($admin, 'web')
            ->get('/customers?status=suspended')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'suspended')
                ->has('customers.data', 1)
                ->where('customers.data.0.status', 'suspended'));
    }

    public function test_admins_can_view_customer_detail(): void
    {
        $admin = Admin::factory()->create();
        $customer = User::factory()->create();
        $account = BroadbandAccount::factory()->create([
            'user_id' => $customer->id,
            'customer_name' => $customer->name,
        ]);
        CustomerPackage::factory()->create([
            'user_id' => $customer->id,
            'broadband_account_id' => $account->id,
        ]);
        $wallet = Wallet::factory()->create(['user_id' => $customer->id, 'balance_mmk' => 15000]);
        WalletTransaction::factory()->create(['wallet_id' => $wallet->id, 'amount' => 5000]);

        $this->actingAs($admin, 'web')
            ->get('/customers/'.$customer->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Customer/Show')
                ->where('customer.id', $customer->id)
                ->where('customer.name', $customer->name)
                ->has('broadbandAccounts', 1)
                ->has('packages', 1)
                ->where('wallet.balance_mmk', '15000.00')
                ->has('wallet.transactions', 1));
    }

    public function test_admin_can_suspend_and_reactivate_a_customer(): void
    {
        $admin = Admin::factory()->create();
        $customer = User::factory()->create(['status' => UserStatus::Active]);

        $this->actingAs($admin, 'web')
            ->patch('/customers/'.$customer->id.'/status', ['status' => 'suspended'])
            ->assertRedirect();

        $this->assertSame(UserStatus::Suspended, $customer->fresh()->status);
        $this->assertDatabaseHas('activity_log', [
            'description' => 'customer_status_updated',
            'subject_id' => $customer->id,
            'causer_id' => $admin->id,
        ]);

        $this->actingAs($admin, 'web')
            ->patch('/customers/'.$customer->id.'/status', ['status' => 'active'])
            ->assertRedirect();

        $this->assertSame(UserStatus::Active, $customer->fresh()->status);
    }

    public function test_admin_can_bind_and_unbind_a_broadband_account(): void
    {
        $admin = Admin::factory()->create();
        $customer = User::factory()->create();
        $account = BroadbandAccount::factory()->unbound()->create([
            'account_number' => 'CG99999999',
        ]);

        $this->actingAs($admin, 'web')
            ->post('/customers/'.$customer->id.'/accounts', ['account_number' => 'CG99999999'])
            ->assertRedirect();

        $this->assertSame($customer->id, $account->fresh()->user_id);
        $this->assertDatabaseHas('activity_log', [
            'description' => 'broadband_account_bound',
            'subject_id' => $customer->id,
        ]);

        $this->actingAs($admin, 'web')
            ->delete('/customers/'.$customer->id.'/accounts/'.$account->id)
            ->assertRedirect();

        $this->assertNull($account->fresh()->user_id);
        $this->assertDatabaseHas('activity_log', [
            'description' => 'broadband_account_unbound',
            'subject_id' => $customer->id,
        ]);
    }

    public function test_admin_cannot_bind_an_account_owned_by_another_customer(): void
    {
        $admin = Admin::factory()->create();
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $account = BroadbandAccount::factory()->create([
            'user_id' => $other->id,
            'account_number' => 'CG88888888',
        ]);

        $this->actingAs($admin, 'web')
            ->post('/customers/'.$customer->id.'/accounts', ['account_number' => 'CG88888888'])
            ->assertRedirect()
            ->assertSessionHasErrors('account_number');

        $this->assertSame($other->id, $account->fresh()->user_id);
    }

    public function test_admins_can_create_a_customer(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'web')
            ->get('/customers/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Customer/Create'));

        $response = $this->actingAs($admin, 'web')
            ->post('/customers', [
                'name' => 'Hla Hla',
                'phone' => '+95911112222',
                'nrc_number' => '12/ABC(N)222222',
                'email' => 'hla@example.test',
                'address' => 'Bahan, Yangon',
                'language_pref' => 'my',
                'status' => 'active',
            ]);

        $customer = User::query()->where('phone', '+95911112222')->first();

        $this->assertNotNull($customer);
        $response->assertRedirect('/customers/'.$customer->id);
        $this->assertDatabaseHas('users', [
            'name' => 'Hla Hla',
            'phone' => '+95911112222',
            'nrc_number' => '12/ABC(N)222222',
            'email' => 'hla@example.test',
        ]);
        $this->assertDatabaseHas('wallets', [
            'user_id' => $customer->id,
            'balance_mmk' => '0.00',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'description' => 'customer_created',
            'subject_id' => $customer->id,
            'causer_id' => $admin->id,
        ]);
    }

    public function test_create_rejects_duplicate_phone_numbers(): void
    {
        $admin = Admin::factory()->create();
        User::factory()->create(['phone' => '+95933334444']);

        $this->actingAs($admin, 'web')
            ->post('/customers', [
                'name' => 'Duplicate Phone',
                'phone' => '+95933334444',
                'nrc_number' => '12/ABC(N)333333',
                'language_pref' => 'en',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('phone');
    }

    public function test_admins_can_update_a_customer(): void
    {
        $admin = Admin::factory()->create();
        $customer = User::factory()->create([
            'name' => 'Old Name',
            'phone' => '+95955556666',
            'status' => UserStatus::Active,
        ]);

        $this->actingAs($admin, 'web')
            ->get('/customers/'.$customer->id.'/edit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Customer/Edit')
                ->where('customer.id', $customer->id)
                ->where('customer.name', 'Old Name'));

        $this->actingAs($admin, 'web')
            ->put('/customers/'.$customer->id, [
                'name' => 'New Name',
                'phone' => '+95955556666',
                'nrc_number' => $customer->nrc_number,
                'email' => 'updated@example.test',
                'address' => 'Kamayut, Yangon',
                'language_pref' => 'en',
                'status' => 'suspended',
            ])
            ->assertRedirect('/customers/'.$customer->id);

        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'name' => 'New Name',
            'email' => 'updated@example.test',
            'address' => 'Kamayut, Yangon',
            'language_pref' => 'en',
            'status' => 'suspended',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'description' => 'customer_updated',
            'subject_id' => $customer->id,
            'causer_id' => $admin->id,
        ]);
    }
}
