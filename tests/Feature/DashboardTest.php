<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\ReviewStatus;
use App\Models\Admin;
use App\Models\Area;
use App\Models\FailureReport;
use App\Models\InstallationApplication;
use App\Models\NotificationCustom;
use App\Models\Payment;
use App\Models\Region;
use App\Models\RelocationRequest;
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
                ->has('regionChart')
                ->has('requestTypeChart')
                ->has('recentRequests')
                ->has('unreadNotifications')
                ->has('recentNotifications')
                ->has('locale')
                ->has('translations'));
    }

    public function test_dashboard_groups_installations_by_region(): void
    {
        $admin = Admin::factory()->create();
        $yangon = Region::factory()->create(['name_en' => 'Yangon']);
        $mandalay = Region::factory()->create(['name_en' => 'Mandalay']);
        $yangonArea = Area::factory()->create(['region_id' => $yangon->id]);
        $mandalayArea = Area::factory()->create(['region_id' => $mandalay->id]);

        InstallationApplication::factory()->count(3)->create(['area_id' => $yangonArea->id]);
        InstallationApplication::factory()->create(['area_id' => $mandalayArea->id]);

        $this->actingAs($admin, 'web')
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('regionChart', 2)
                ->where('regionChart.0.name_en', 'Yangon')
                ->where('regionChart.0.value', 3)
                ->where('regionChart.1.name_en', 'Mandalay')
                ->where('regionChart.1.value', 1));
    }

    public function test_dashboard_rolls_extra_regions_into_other(): void
    {
        $admin = Admin::factory()->create();

        foreach (range(1, 6) as $index) {
            $region = Region::factory()->create(['name_en' => "Region {$index}"]);
            $area = Area::factory()->create(['region_id' => $region->id]);
            InstallationApplication::factory()->count(7 - $index)->create(['area_id' => $area->id]);
        }

        $this->actingAs($admin, 'web')
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('regionChart', 5)
                ->where('regionChart.4.id', null)
                ->where('regionChart.4.name_en', 'Other')
                ->where('regionChart.4.value', 3));
    }

    public function test_dashboard_ranks_service_requests_highest_to_lowest(): void
    {
        $admin = Admin::factory()->create();

        InstallationApplication::factory()->count(4)->create();
        FailureReport::factory()->count(2)->create();
        RelocationRequest::factory()->create();

        $this->actingAs($admin, 'web')
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('requestTypeChart.items', 4)
                ->where('requestTypeChart.items.0.type', 'installation')
                ->where('requestTypeChart.items.0.value', 4)
                ->where('requestTypeChart.items.0.percent', 57)
                ->where('requestTypeChart.items.1.type', 'failure')
                ->where('requestTypeChart.items.1.value', 2)
                ->where('requestTypeChart.items.2.type', 'relocation')
                ->where('requestTypeChart.items.2.value', 1)
                ->where('requestTypeChart.items.3.type', 'change_plan')
                ->where('requestTypeChart.items.3.value', 0)
                ->where('requestTypeChart.change', 100));
    }

    public function test_authenticated_admins_receive_recent_notifications(): void
    {
        $admin = Admin::factory()->create();
        NotificationCustom::factory()->create([
            'title' => 'Event Today',
            'body' => 'Just a reminder that you have an event today.',
            'is_read' => false,
            'sent_at' => now()->setTime(9, 15),
        ]);

        $this->actingAs($admin, 'web')
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('unreadNotifications', 1)
                ->has('recentNotifications', 1)
                ->where('recentNotifications.0.title', 'Event Today')
                ->where('recentNotifications.0.time', '9:15 AM'));
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
