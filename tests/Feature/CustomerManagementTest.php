<?php

namespace Tests\Feature;

use App\Enums\LedgerAccountCode;
use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\UserStatus;
use App\Models\Admin;
use App\Models\BillPayment;
use App\Models\CustomerPackage;
use App\Models\Invoice;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\PackageOrder;
use App\Models\Payment;
use App\Models\TopUpCard;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Ledger\LedgerPoster;
use App\Support\AppPermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use LogicException;
use Maatwebsite\Excel\Facades\Excel;
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
        config(['services.broadband.url' => 'https://broadband.test']);
        Http::fake([
            'broadband.test/broadband_accounts*' => Http::response([
                ['account_number' => 'CG12345678', 'customer_name' => 'Aung Aung', 'status' => 'active'],
            ]),
        ]);

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

    public function test_customer_detail_remains_available_when_broadband_service_cannot_be_reached(): void
    {
        config(['services.broadband.url' => 'https://broadband.test']);
        Http::fake(fn() => throw new ConnectionException('DNS lookup failed.'));

        $admin = Admin::factory()->create();
        $customer = User::factory()->create(['broadband_account_number' => 'CG0000007']);

        $this->actingAs($admin, 'web')
            ->get('/customers/' . $customer->id)
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Customer/Show')
                    ->where('accountBinding.account_number', 'CG0000007')
                    ->where('accountBinding.status', 'unknown'),
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
            ->get('/customers/' . $customer->id)
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->where('wallet.transaction_overview.adjustment.credit.count', 1)
                    ->where('wallet.transaction_overview.adjustment.credit.amount', '500')
                    ->where('wallet.transaction_overview.adjustment.debit.count', 1)
                    ->where('wallet.transaction_overview.adjustment.debit.amount', '200'),
            );
    }

    public function test_customer_transactions_detail_loads_without_broadband_api_configuration(): void
    {
        config(['services.broadband.url' => null]);

        $admin = Admin::factory()->create();
        $customer = User::factory()->create(['broadband_account_number' => 'CG12345678']);

        $this->actingAs($admin, 'web')
            ->get('/customers/' . $customer->id . '?transactions=all')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Customer/Show')
                    ->where('customer.id', $customer->id)
                    ->where('accountBinding', null)
                    ->has('transactionPage.data'),
            );
    }

    public function test_customer_view_with_export_permission_can_export_only_that_customers_transactions(): void
    {
        $this->autoGrantPermissions = false;
        RolePermissionSeeder::sync();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['customers.view', AppPermissions::SystemExport]);
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $customerTransaction = LedgerTransaction::factory()->create([
            'wallet_id' => Wallet::factory()->create(['user_id' => $customer->id])->id,
        ]);
        LedgerTransaction::factory()->create([
            'wallet_id' => Wallet::factory()->create(['user_id' => $otherCustomer->id])->id,
        ]);
        Excel::fake();

        $this->actingAs($admin, 'web')
            ->get('/customers/' . $customer->id . '/transactions/export?customer_id=' . $otherCustomer->id)
            ->assertOk();

        Excel::assertDownloaded('customer-transactions.xlsx', function ($export) use ($customerTransaction): bool {
            return $export->query()->get()->modelKeys() === [$customerTransaction->id];
        });
    }

    public function test_customer_transaction_export_requires_system_export_permission(): void
    {
        $this->autoGrantPermissions = false;
        RolePermissionSeeder::sync();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = Admin::factory()->create();
        $admin->givePermissionTo('customers.view');
        $customer = User::factory()->create();

        $this->actingAs($admin, 'web')
            ->get('/customers/' . $customer->id . '/transactions/export')
            ->assertForbidden();
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

    public function test_admin_can_reactivate_a_deactivated_customer(): void
    {
        $admin = Admin::factory()->create();
        $customer = User::factory()->create(['status' => UserStatus::Deactivated]);

        $this->actingAs($admin, 'web')
            ->patch('/customers/' . $customer->id . '/status', ['status' => 'active'])
            ->assertRedirect();

        $this->assertSame(UserStatus::Active, $customer->fresh()->status);
        $this->assertDatabaseHas('activity_log', [
            'description' => 'customer_status_updated',
            'subject_id' => $customer->id,
            'causer_id' => $admin->id,
        ]);
    }

    public function test_admin_cannot_set_customer_status_to_deactivated(): void
    {
        $admin = Admin::factory()->create();
        $customer = User::factory()->create(['status' => UserStatus::Active]);

        $this->actingAs($admin, 'web')
            ->patch('/customers/' . $customer->id . '/status', ['status' => 'deactivated'])
            ->assertSessionHasErrors('status');

        $this->assertSame(UserStatus::Active, $customer->fresh()->status);
    }

    public function test_customer_edit_can_preserve_existing_deactivated_status(): void
    {
        $admin = Admin::factory()->create();
        $customer = User::factory()->create(['status' => UserStatus::Deactivated]);

        $this->actingAs($admin, 'web')
            ->put('/customers/' . $customer->id, [
                'name' => 'Updated Customer',
                'phone' => $customer->phone,
                'status' => 'deactivated',
            ])
            ->assertRedirect('/customers/' . $customer->id);

        $this->assertSame('Updated Customer', $customer->fresh()->name);
        $this->assertSame(UserStatus::Deactivated, $customer->fresh()->status);
    }

    public function test_suspending_a_customer_revokes_their_api_tokens(): void
    {
        $admin = Admin::factory()->create();
        $customer = User::factory()->create(['status' => UserStatus::Active]);
        $customer->createToken('flutter');
        $customer->deviceTokens()->create(['token' => 'device-token', 'platform' => 'android']);

        $this->actingAs($admin, 'web')
            ->patch('/customers/' . $customer->id . '/status', ['status' => 'suspended'])
            ->assertRedirect();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('device_tokens', 0);
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
            'phone' => '+95921112222',
            'password' => '123456',
            'password_confirmation' => '123456',
            'status' => 'active',
        ]);

        // Typed with a "+", stored in the canonical format (no "+") shared with the app.
        $customer = User::query()->where('phone', '95921112222')->first();

        $this->assertNotNull($customer);
        $response->assertRedirect('/customers/' . $customer->id);
        $this->assertDatabaseHas('users', [
            'name' => 'Hla Hla',
            'phone' => '95921112222',
        ]);
        $this->assertTrue(Hash::check('123456', $customer->password));
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
                'password' => '123456',
                'password_confirmation' => '123456',
                'status' => 'active',
            ])
            ->assertSessionHasErrors('phone');
    }

    public function test_admin_customer_password_must_be_six_digits(): void
    {
        $admin = Admin::factory()->create();

        foreach (['12345', '1234567', '12ab56'] as $password) {
            $this->actingAs($admin, 'web')
                ->post('/customers', [
                    'name' => 'Invalid PIN',
                    'phone' => '+95922223333',
                    'password' => $password,
                    'password_confirmation' => $password,
                    'status' => 'active',
                ])
                ->assertSessionHasErrors('password');
        }

        $this->assertDatabaseMissing('users', ['phone' => '95922223333']);
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

    public function test_admin_password_reset_signs_the_customer_out_and_is_audited_without_the_password(): void
    {
        $admin = Admin::factory()->create();
        $customer = User::factory()->create([
            'phone' => '+95955556666',
            'status' => UserStatus::Active,
        ]);
        $customer->createToken('flutter');
        $customer->deviceTokens()->create(['token' => 'device-token', 'platform' => 'android']);

        $this->actingAs($admin, 'web')
            ->put('/customers/' . $customer->id, [
                'name' => $customer->name,
                'phone' => '+95955556666',
                'status' => 'active',
                'password' => '654321',
                'password_confirmation' => '654321',
            ])
            ->assertRedirect('/customers/' . $customer->id);

        $this->assertTrue(Hash::check('654321', $customer->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('device_tokens', 0);

        $log = \Illuminate\Support\Facades\DB::table('activity_log')
            ->where('description', 'customer_updated')
            ->where('subject_id', $customer->id)
            ->first();
        $this->assertNotNull($log);
        $this->assertEquals($admin->id, $log->causer_id);

        $properties = json_decode($log->properties, true);
        $this->assertTrue($properties['password_reset']);
        $this->assertArrayNotHasKey('password', $properties);
        $this->assertStringNotContainsString('654321', $log->properties);
    }

    public function test_admin_edit_without_a_password_keeps_sessions_and_is_not_marked_as_a_reset(): void
    {
        $admin = Admin::factory()->create();
        $customer = User::factory()->create([
            'phone' => '+95955556666',
            'status' => UserStatus::Active,
        ]);
        $customer->createToken('flutter');

        $this->actingAs($admin, 'web')
            ->put('/customers/' . $customer->id, [
                'name' => 'Renamed Customer',
                'phone' => '+95955556666',
                'status' => 'active',
            ])
            ->assertRedirect('/customers/' . $customer->id);

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $log = \Illuminate\Support\Facades\DB::table('activity_log')
            ->where('description', 'customer_updated')
            ->where('subject_id', $customer->id)
            ->first();
        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('password_reset', json_decode($log->properties, true));
    }

    public function test_customer_deletion_is_rejected(): void
    {
        $admin = Admin::factory()->create();
        $customer = User::factory()->create();

        $this->actingAs($admin, 'web')
            ->from('/customers')
            ->delete('/customers/' . $customer->id)
            ->assertForbidden();

        $this->assertNotSoftDeleted($customer);
    }

    public function test_bulk_customer_deletion_is_rejected(): void
    {
        $admin = Admin::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($admin, 'web')
            ->from('/customers')
            ->delete('/customers/bulk-destroy', ['ids' => [$first->id, $second->id]])
            ->assertForbidden();

        $this->assertNotSoftDeleted($first);
        $this->assertNotSoftDeleted($second);
    }

    public function test_customer_model_blocks_soft_and_force_deletion(): void
    {
        $customer = User::factory()->create();

        try {
            $customer->delete();
            $this->fail('Soft deletion should be prohibited.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('cannot be deleted', $exception->getMessage());
        }

        try {
            $customer->forceDelete();
            $this->fail('Hard deletion should be prohibited.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('cannot be deleted', $exception->getMessage());
        }

        $this->assertDatabaseHas('users', ['id' => $customer->id, 'deleted_at' => null]);
    }

    public function test_financial_records_cannot_be_deleted(): void
    {
        foreach ([
            BillPayment::class,
            CustomerPackage::class,
            Invoice::class,
            LedgerAccount::class,
            LedgerEntry::class,
            LedgerTransaction::class,
            PackageOrder::class,
            Payment::class,
            TopUpCard::class,
            Wallet::class,
        ] as $recordType) {
            $record = new $recordType();
            $record->exists = true;

            try {
                $record->delete();
                $this->fail($recordType . ' deletion should be prohibited.');
            } catch (LogicException $exception) {
                $this->assertStringContainsString('cannot be deleted', $exception->getMessage());
            }

            if (method_exists($record, 'forceDelete')) {
                try {
                    $record->forceDelete();
                    $this->fail($recordType . ' hard deletion should be prohibited.');
                } catch (LogicException $exception) {
                    $this->assertStringContainsString('cannot be deleted', $exception->getMessage());
                }
            }
        }
    }

    public function test_customer_deletion_remains_blocked_without_delete_permission(): void
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
