<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_report_shows_date_scoped_status_counts(): void
    {
        User::factory()->create([
            'name' => 'Active Customer',
            'status' => 'active',
            'created_at' => '2026-09-10 10:00:00',
        ]);
        User::factory()
            ->suspended()
            ->create([
                'name' => 'Suspended Customer',
                'created_at' => '2026-09-12 10:00:00',
            ]);
        User::factory()->create(['created_at' => '2026-08-01 10:00:00']);

        $this->actingAs(Admin::factory()->create(), 'web')
            ->get('/reports/customers?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Reports/CustomerReports/Index')
                    ->where('summary.total_users', 2)
                    ->where('summary.active_users', 1)
                    ->where('summary.suspended_users', 1)
                    ->where('summary.connected_users', 0)
                    ->where('summary.not_connected_users', 2),
            );
    }

    public function test_customer_report_can_filter_by_year(): void
    {
        User::factory()->create(['created_at' => '2026-05-10 10:00:00']);
        User::factory()->create(['created_at' => '2025-05-10 10:00:00']);

        $this->actingAs(Admin::factory()->create(), 'web')
            ->get('/reports/customers?mode=yearly&year=2026')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->where('filters.mode', 'yearly')
                    ->where('filters.year', '2026')
                    ->where('summary.total_users', 1)
                    ->where('summary.active_users', 1)
                    ->where('summary.suspended_users', 0),
            );
    }

    public function test_customer_report_includes_broadband_and_wallet_point_totals(): void
    {
        $connectedUser = User::factory()->create([
            'broadband_account_number' => 'BB-123456',
            'created_at' => '2026-09-29 10:00:00',
        ]);
        Wallet::factory()->create([
            'user_id' => $connectedUser->id,
            'balance' => 1250,
        ]);
        User::factory()->create([
            'broadband_account_number' => null,
            'created_at' => '2026-09-28 10:00:00',
        ]);

        $this->actingAs(Admin::factory()->create(), 'web')
            ->get('/reports/customers?mode=yearly&year=2026')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->where('summary.connected_users', 1)
                    ->where('summary.total_wallet_points', 1250)
                    ->where('summary.not_connected_users', 1),
            );
    }
}
