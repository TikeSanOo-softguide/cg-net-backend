<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use App\Support\AppPermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NavigationStackTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_opened_from_transactions_pops_back_without_a_loop(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create();
        $transactions = rtrim((string) config('app.url'), '/') . '/billing/transactions?search=FTTH-1';

        $this->actingAs($admin)
            ->get('/billing/transactions?search=FTTH-1', ['X-Navigation' => 'reset'])
            ->assertOk()
            ->assertInertia(
                fn(AssertableInertia $page) => $page
                    ->component('BillPayment/Transactions/Index')
                    ->where('return_to', null),
            );

        $this->actingAs($admin)
            ->get("/customers/{$customer->id}", ['referer' => $transactions])
            ->assertOk()
            ->assertInertia(
                fn(AssertableInertia $page) => $page
                    ->component('Customer/Show')
                    ->where('return_to', '/billing/transactions?search=FTTH-1'),
            );

        $this->actingAs($admin)
            ->get('/billing/transactions?search=FTTH-1', [
                'referer' => rtrim((string) config('app.url'), '/') . "/customers/{$customer->id}",
                'X-Navigation' => 'pop',
            ])
            ->assertOk()
            ->assertInertia(
                fn(AssertableInertia $page) => $page
                    ->component('BillPayment/Transactions/Index')
                    ->where('return_to', null),
            );
    }

    public function test_customer_list_is_remembered_when_a_customer_is_opened(): void
    {
        $admin = $this->admin();
        $customer = User::factory()->create();
        $list = rtrim((string) config('app.url'), '/') . '/customers?search=demo&status=active';

        $this->actingAs($admin)
            ->get('/customers?search=demo&status=active', ['X-Navigation' => 'reset'])
            ->assertOk();

        $this->actingAs($admin)
            ->get("/customers/{$customer->id}", ['referer' => $list])
            ->assertOk()
            ->assertInertia(
                fn(AssertableInertia $page) => $page
                    ->component('Customer/Show')
                    ->where('return_to', '/customers?search=demo&status=active'),
            );
    }

    public function test_explicit_return_target_is_preserved_for_billing_pages(): void
    {
        $admin = $this->admin();
        $returnTo = '/billing/transactions?search=FTTH-1&open_transaction=FTTH-1';

        $this->actingAs($admin)
            ->get('/billing/bill-payments?' . http_build_query([
                'payment_id' => 1,
                'return_to' => $returnTo,
            ]))
            ->assertOk()
            ->assertInertia(
                fn(AssertableInertia $page) => $page
                    ->component('BillPayment/BillPayment/Index')
                    ->where('return_to', $returnTo),
            );

        $this->actingAs($admin)
            ->get('/billing/transactions?' . http_build_query([
                'return_to' => '/billing/bill-payments?payment_id=1',
            ]))
            ->assertOk()
            ->assertInertia(
                fn(AssertableInertia $page) => $page
                    ->component('BillPayment/Transactions/Index')
                    ->where('return_to', '/billing/bill-payments?payment_id=1'),
            );
    }

    public function test_menu_resets_a_trail_that_started_on_reports(): void
    {
        $admin = $this->admin();
        $reports = rtrim((string) config('app.url'), '/') . '/reports';

        $this->actingAs($admin)
            ->get('/reports', ['X-Navigation' => 'reset'])
            ->assertOk()
            ->assertInertia(fn(AssertableInertia $page) => $page->where('return_to', null));

        $this->actingAs($admin)
            ->get('/reports/ledger-health?snapshot=4', ['referer' => $reports])
            ->assertOk()
            ->assertInertia(fn(AssertableInertia $page) => $page->where('return_to', '/reports'));

        $this->actingAs($admin)
            ->get('/billing/transactions', [
                'referer' => rtrim((string) config('app.url'), '/') . '/reports/ledger-health?snapshot=4',
                'X-Navigation' => 'reset',
            ])
            ->assertOk()
            ->assertInertia(
                fn(AssertableInertia $page) => $page
                    ->component('BillPayment/Transactions/Index')
                    ->where('return_to', null),
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
