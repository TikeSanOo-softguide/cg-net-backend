<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\ReviewStatus;
use App\Models\Admin;
use App\Models\FailureReport;
use App\Models\InstallationApplication;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_the_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_admins_see_dashboard_overview_props(): void
    {
        $admin = Admin::factory()->create();
        User::factory()->count(3)->create();
        Payment::factory()->create([
            'status' => PaymentStatus::Paid,
            'amount' => 15000,
            'paid_at' => now(),
        ]);
        InstallationApplication::factory()->create([
            'status' => ReviewStatus::UnderReview,
        ]);

        $this->actingAs($admin, 'web')
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index')
                ->has('stats.total_customers')
                ->has('stats.active_broadband_accounts')
                ->has('stats.active_packages')
                ->where('stats.todays_revenue', '15000.00')
                ->where('stats.pending_requests', fn ($count) => $count >= 1)
                ->has('chart', 30)
                ->has('recentRequests')
                ->has('unreadNotifications')
                ->has('locale')
                ->has('translations'));
    }

    public function test_admins_can_bulk_delete_recent_service_requests(): void
    {
        $admin = Admin::factory()->create();
        $install = InstallationApplication::factory()->create();
        $failure = FailureReport::factory()->create();

        $this->actingAs($admin, 'web')
            ->from('/dashboard')
            ->delete('/dashboard/requests/bulk-destroy', [
                'ids' => ['installation-'.$install->id, 'failure-'.$failure->id],
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHas('success', 'common.bulk_deleted');

        $this->assertSoftDeleted($install);
        $this->assertSoftDeleted($failure);
    }

    public function test_admins_without_service_request_delete_cannot_bulk_delete_requests(): void
    {
        $this->autoGrantPermissions = false;
        RolePermissionSeeder::sync();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $admin = Admin::factory()->create();
        $admin->givePermissionTo('dashboard.view');
        $install = InstallationApplication::factory()->create();

        $this->actingAs($admin, 'web')
            ->delete('/dashboard/requests/bulk-destroy', [
                'ids' => ['installation-'.$install->id],
            ])
            ->assertForbidden();

        $this->assertNotSoftDeleted($install);
    }
}
