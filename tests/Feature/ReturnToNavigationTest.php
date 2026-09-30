<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Support\AppPermissions;
use App\Support\ReturnTo;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReturnToNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ledger_health_remembers_return_targets_for_customers_and_transactions(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/reports/ledger-health?snapshot=1')
            ->assertOk();

        $this->assertSame('/reports/ledger-health?snapshot=1', ReturnTo::get('customers.show'));
        $this->assertSame('/reports/ledger-health?snapshot=1', ReturnTo::get('transactions.index'));
    }

    public function test_transactions_index_captures_ledger_health_referer_as_return_to(): void
    {
        $admin = $this->admin();
        $referer = rtrim((string) config('app.url'), '/').'/reports/ledger-health?snapshot=4';

        $this->actingAs($admin)
            ->withHeader('referer', $referer)
            ->get('/billing/transactions?search=FTTH-1&open_transaction=FTTH-1')
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Transactions/Index')
                    ->where('return_to', '/reports/ledger-health?snapshot=4'),
            );
    }

    public function test_customer_show_uses_session_return_to_from_customer_index(): void
    {
        $admin = $this->admin();
        $customer = \App\Models\User::factory()->create();

        $this->actingAs($admin)
            ->get('/customers?search=demo&status=active')
            ->assertOk();

        $this->actingAs($admin)
            ->get("/customers/{$customer->id}")
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Customer/Show')
                    ->where('return_to', '/customers?search=demo&status=active'),
            );
    }

    private function admin(): Admin
    {
        RolePermissionSeeder::sync();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = Admin::factory()->create();
        $admin->givePermissionTo(AppPermissions::names());

        return $admin;
    }
}
