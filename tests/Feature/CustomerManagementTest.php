<?php

namespace Tests\Feature;

use App\Enums\LedgerAccountCode;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\UserStatus;
use App\Models\Admin;
use App\Models\CustomerPackage;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Ledger\LedgerPoster;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
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
            'status' => UserStatus::Active,
        ]);
        User::factory()
            ->suspended()
            ->create([
                'name' => 'Hidden User',
                'phone' => '09222222222',
            ]);

        $this->actingAs($admin, 'web')
            ->get('/customers?search=Aung&sort=name&direction=asc')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Customer/Index')
                    ->where('filters.search', 'Aung')
                    ->where('filters.sort', 'name')
                    ->has('customers.data', 1)
                    ->where('customers.data.0.id', $match->id)
                    ->where('customers.data.0.name', 'Aung Aung'),
            );

        $this->actingAs($admin, 'web')
            ->get('/customers?status=suspended')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->where('filters.status', 'suspended')
                    ->has('customers.data', 1)
                    ->where('customers.data.0.status', 'suspended'),
            );
    }

    public function test_admins_can_view_customer_detail(): void
    {
        $admin = Admin::factory()->create();
        $customer = User::factory()->create(['broadband_account_number' => 'CG12345678']);
        CustomerPackage::factory()->create([
            'user_id' => $customer->id,
        ]);
        $wallet = Wallet::factory()->create(['user_id' => $customer->id, 'balance' => 15000]);
        LedgerTransaction::factory()->create(['wallet_id' => $wallet->id, 'amount' => 5000]);

        $this->actingAs($admin, 'web')
            ->get('/customers/' . $customer->id)
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Customer/Show')
                    ->where('customer.id', $customer->id)
                    ->where('customer.name', $customer->name)
                    ->where('accountBinding.account_number', 'CG12345678')
                    ->has('packageHistory', 1)
                    ->where('wallet.balance', '15000')
                    ->has('wallet.transactions', 1),
            );
    }

    public function test_customer_detail_summarizes_adjustment_credits_and_debits_separately(): void
    {
        $admin = Admin::factory()->create();
        $customer = User::factory()->create();
        $wallet = Wallet::factory()->create(['user_id' => $customer->id, 'balance' => 1500]);
        $ledger = app(LedgerPoster::class);
        $ledger->ensureSystemAccounts();
        $ledger->ensureCustomerLiabilityAccount($wallet);
        $ledger->creditWallet(
            wallet: $wallet,
            amount: 500,
            contraAccount: LedgerAccountCode::AdjustmentExpense,
            type: LedgerTransactionType::Adjustment,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: 'customer-detail-adjustment-credit',
        );
        $ledger->debitWallet(
            wallet: $wallet->fresh(),
            amount: 200,
            contraAccount: LedgerAccountCode::AdjustmentExpense,
            type: LedgerTransactionType::Adjustment,
            status: LedgerTransactionStatus::Completed,
            idempotencyKey: 'customer-detail-adjustment-debit',
        );

        $this->actingAs($admin, 'web')
            ->get('/customers/'.$customer->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('wallet.transaction_overview.adjustment.credit.count', 1)
                ->where('wallet.transaction_overview.adjustment.credit.amount', '500')
                ->where('wallet.transaction_overview.adjustment.debit.count', 1)
                ->where('wallet.transaction_overview.adjustment.debit.amount', '200'));
    }

    public function test_admin_can_suspend_and_reactivate_a_customer(): void
    {
        $admin = Admin::factory()->create();
        $customer = User::factory()->create(['status' => UserStatus::Active]);

        $this->actingAs($admin, 'web')
            ->patch('/customers/' . $customer->id . '/status', ['status' => 'suspended'])
            ->assertRedirect();

        $this->assertSame(UserStatus::Suspended, $customer->fresh()->status);
        $this->assertDatabaseHas('activity_log', [
            'description' => 'customer_status_updated',
            'subject_id' => $customer->id,
            'causer_id' => $admin->id,
        ]);

        $this->actingAs($admin, 'web')
            ->patch('/customers/' . $customer->id . '/status', ['status' => 'active'])
            ->assertRedirect();

        $this->assertSame(UserStatus::Active, $customer->fresh()->status);
    }

    public function test_admin_can_bind_and_unbind_a_broadband_account_number(): void
    {
        $admin = Admin::factory()->create();
        $customer = User::factory()->create();

        $this->actingAs($admin, 'web')
            ->post('/customers/' . $customer->id . '/accounts', ['account_number' => 'CG99999999'])
            ->assertRedirect();

        $this->assertSame('CG99999999', $customer->fresh()->broadband_account_number);
        $this->assertDatabaseHas('activity_log', [
            'description' => 'broadband_account_bound',
            'subject_id' => $customer->id,
        ]);

        $this->actingAs($admin, 'web')
            ->delete('/customers/' . $customer->id . '/accounts')
            ->assertRedirect();

        $this->assertNull($customer->fresh()->broadband_account_number);
        $this->assertDatabaseHas('activity_log', [
            'description' => 'broadband_account_unbound',
            'subject_id' => $customer->id,
        ]);
    }

    public function test_admin_cannot_bind_an_account_owned_by_another_customer(): void
    {
        $admin = Admin::factory()->create();
        $customer = User::factory()->create();
        $other = User::factory()->create(['broadband_account_number' => 'CG88888888']);

        $this->actingAs($admin, 'web')
            ->post('/customers/' . $customer->id . '/accounts', ['account_number' => 'CG88888888'])
            ->assertRedirect()
            ->assertSessionHasErrors('account_number');

        $this->assertSame('CG88888888', $other->fresh()->broadband_account_number);
    }

    public function test_admins_can_create_a_customer(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'web')->get('/customers/create')->assertRedirect('/customers');

        $response = $this->actingAs($admin, 'web')->post('/customers', [
            'name' => 'Hla Hla',
            'phone' => '+95911112222',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'status' => 'active',
        ]);

        $customer = User::query()->where('phone', '+95911112222')->first();

        $this->assertNotNull($customer);
        $response->assertRedirect('/customers/' . $customer->id);
        $this->assertDatabaseHas('users', [
            'name' => 'Hla Hla',
            'phone' => '+95911112222',
        ]);
        $this->assertTrue(Hash::check('password123', $customer->password));
        $this->assertDatabaseHas('wallets', [
            'user_id' => $customer->id,
            'balance' => 0,
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
                'password' => 'password123',
                'password_confirmation' => 'password123',
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
            ->get('/customers/' . $customer->id . '/edit')
            ->assertRedirect('/customers/' . $customer->id);

        $this->actingAs($admin, 'web')
            ->put('/customers/' . $customer->id, [
                'name' => 'New Name',
                'phone' => '+95955556666',
                'status' => 'suspended',
            ])
            ->assertRedirect('/customers/' . $customer->id);

        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'name' => 'New Name',
            'status' => 'suspended',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'description' => 'customer_updated',
            'subject_id' => $customer->id,
            'causer_id' => $admin->id,
        ]);
    }

    public function test_admins_can_delete_a_customer(): void
    {
        $admin = Admin::factory()->create();
        $customer = User::factory()->create();

        $this->actingAs($admin, 'web')
            ->from('/customers')
            ->delete('/customers/' . $customer->id)
            ->assertRedirect('/customers')
            ->assertSessionHas('success', 'customers.deleted');

        $this->assertSoftDeleted($customer);
        $this->assertDatabaseHas('activity_log', [
            'description' => 'customer_deleted',
            'subject_id' => $customer->id,
            'causer_id' => $admin->id,
        ]);
    }

    public function test_admins_can_bulk_delete_customers(): void
    {
        $admin = Admin::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($admin, 'web')
            ->from('/customers')
            ->delete('/customers/bulk-destroy', ['ids' => [$first->id, $second->id]])
            ->assertRedirect('/customers')
            ->assertSessionHas('success', 'common.bulk_deleted');

        $this->assertSoftDeleted($first);
        $this->assertSoftDeleted($second);
    }

    public function test_admins_without_customer_delete_cannot_bulk_delete_customers(): void
    {
        $this->autoGrantPermissions = false;
        RolePermissionSeeder::sync();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $admin = Admin::factory()->create();
        $admin->givePermissionTo('customers.view');
        $customer = User::factory()->create();

        $this->actingAs($admin, 'web')
            ->delete('/customers/bulk-destroy', ['ids' => [$customer->id]])
            ->assertForbidden();

        $this->assertNotSoftDeleted($customer);
    }
}
