<?php

namespace Tests\Feature;

use App\Events\AdminNotificationCreated;
use App\Events\ServiceRequestSubmitted;
use App\Listeners\CreateAdminNotification;
use App\Models\Admin;
use App\Models\AdminNotification;
use App\Services\AdminNotification\AdminNotificationDelivery;
use App\Support\AppPermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_request_notifications_only_go_to_active_admins_with_related_permissions(): void
    {
        RolePermissionSeeder::sync();
        Event::fake([AdminNotificationCreated::class]);

        $relatedAdmin = Admin::factory()->create();
        $relatedAdmin->givePermissionTo(['notifications.view', 'service-requests.view']);

        $reportsAdmin = Admin::factory()->create();
        $reportsAdmin->givePermissionTo(['notifications.view', 'reports.view']);

        $inactiveAdmin = Admin::factory()->inactive()->create();
        $inactiveAdmin->givePermissionTo(['notifications.view', 'service-requests.view']);

        app(CreateAdminNotification::class)->handle(new ServiceRequestSubmitted('installation_application', 42));

        $notification = AdminNotification::query()->sole();

        $this->assertSame([$relatedAdmin->id], $notification->admins()->pluck('admins.id')->all());
        $this->assertDatabaseHas('admin_notification_recipients', [
            'admin_notification_id' => $notification->id,
            'admin_id' => $relatedAdmin->id,
            'is_read' => false,
            'read_at' => null,
        ]);
        Event::assertDispatched(AdminNotificationCreated::class, fn (AdminNotificationCreated $event): bool => $event->admin->is($relatedAdmin));
    }

    public function test_ledger_notifications_require_report_and_notification_permissions(): void
    {
        RolePermissionSeeder::sync();
        Event::fake([AdminNotificationCreated::class]);

        $reportsAdmin = Admin::factory()->create();
        $reportsAdmin->givePermissionTo(['notifications.view', 'reports.view']);

        $serviceRequestsAdmin = Admin::factory()->create();
        $serviceRequestsAdmin->givePermissionTo(['notifications.view', 'service-requests.view']);

        $notification = AdminNotification::query()->create([
            'type' => 'other',
            'title' => 'Ledger Health Check Completed',
            'body' => 'The Daily ledger health check completed successfully.',
            'reference_type' => 'ledger_health_snapshot_daily',
            'reference_id' => 4,
        ]);
        app(AdminNotificationDelivery::class)->deliver($notification, 'reports.view');

        $this->assertDatabaseHas('admin_notification_recipients', [
            'admin_notification_id' => $notification->id,
            'admin_id' => $reportsAdmin->id,
        ]);
        $this->assertDatabaseMissing('admin_notification_recipients', [
            'admin_notification_id' => $notification->id,
            'admin_id' => $serviceRequestsAdmin->id,
        ]);
    }

    public function test_ledger_delivery_reads_role_permissions_after_runtime_permission_changes(): void
    {
        RolePermissionSeeder::sync();
        Event::fake([AdminNotificationCreated::class]);

        $role = Role::query()->create(['name' => 'Ledger Reviewer', 'guard_name' => 'web']);
        $admin = Admin::factory()->create();
        $admin->assignRole($role);

        $delivery = app(AdminNotificationDelivery::class);
        $firstNotification = AdminNotification::query()->create([
            'type' => 'other',
            'title' => 'Ledger Health Check Completed',
            'body' => 'The Daily ledger health check completed successfully.',
            'reference_type' => 'ledger_health_snapshot_daily',
            'reference_id' => 9,
        ]);
        $delivery->deliver($firstNotification, 'reports.view');

        $this->assertDatabaseMissing('admin_notification_recipients', [
            'admin_notification_id' => $firstNotification->id,
            'admin_id' => $admin->id,
        ]);

        DB::table('role_has_permissions')->insert(
            Permission::query()
                ->whereIn('name', ['notifications.view', 'reports.view'])
                ->where('guard_name', 'web')
                ->get(['id'])
                ->map(fn (Permission $permission): array => [
                    'permission_id' => $permission->id,
                    'role_id' => $role->id,
                ])
                ->all(),
        );
        $delivery->forgetCachedRecipients();

        $notification = AdminNotification::query()->create([
            'type' => 'other',
            'title' => 'Ledger Health Check Completed',
            'body' => 'The Daily ledger health check completed successfully.',
            'reference_type' => 'ledger_health_snapshot_daily',
            'reference_id' => 10,
        ]);

        $delivery->deliver($notification, 'reports.view');

        $this->assertDatabaseHas('admin_notification_recipients', [
            'admin_notification_id' => $notification->id,
            'admin_id' => $admin->id,
        ]);
    }

    public function test_super_admin_receives_ledger_health_notification_as_recipient(): void
    {
        RolePermissionSeeder::sync();
        Event::fake([AdminNotificationCreated::class]);

        $superAdmin = Admin::factory()->create();
        $superAdmin->assignRole(AppPermissions::SuperAdmin);
        $notification = AdminNotification::query()->create([
            'type' => 'other',
            'title' => 'Ledger Health Check Completed',
            'body' => 'The Daily ledger health check completed successfully.',
            'reference_type' => 'ledger_health_snapshot_daily',
            'reference_id' => 7,
        ]);

        app(AdminNotificationDelivery::class)->deliver($notification, 'reports.view');

        $this->assertDatabaseHas('admin_notification_recipients', [
            'admin_notification_id' => $notification->id,
            'admin_id' => $superAdmin->id,
            'is_read' => false,
            'read_at' => null,
        ]);
        Event::assertDispatched(
            AdminNotificationCreated::class,
            fn (AdminNotificationCreated $event): bool => $event->admin->is($superAdmin),
        );
    }

    public function test_read_status_and_notification_listing_are_private_to_each_admin(): void
    {
        $firstAdmin = Admin::factory()->create();
        $secondAdmin = Admin::factory()->create();
        $notification = AdminNotification::query()->create([
            'type' => 'request',
            'title' => 'New Request',
            'body' => 'A request was submitted.',
        ]);
        $notification->admins()->attach([
            $firstAdmin->id => ['is_read' => false],
            $secondAdmin->id => ['is_read' => false],
        ]);

        $this->actingAs($firstAdmin, 'web')
            ->getJson('/dashboard/notifications?limit=5')
            ->assertOk()
            ->assertJsonPath('total_count', 1)
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.read_at', null);

        $this->actingAs($firstAdmin, 'web')
            ->putJson("/dashboard/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $notification->id)
            ->assertJsonPath('data.read_at', fn (?string $value): bool => $value !== null);

        $this->assertDatabaseHas('admin_notification_recipients', [
            'admin_notification_id' => $notification->id,
            'admin_id' => $firstAdmin->id,
            'is_read' => true,
        ]);
        $this->assertDatabaseHas('admin_notification_recipients', [
            'admin_notification_id' => $notification->id,
            'admin_id' => $secondAdmin->id,
            'is_read' => false,
            'read_at' => null,
        ]);

        $this->actingAs($secondAdmin, 'web')
            ->getJson('/dashboard/notifications?limit=5')
            ->assertOk()
            ->assertJsonPath('total_count', 1)
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.read_at', null);
    }

    public function test_shared_admin_notification_total_counts_only_the_current_admins_recipients(): void
    {
        $admin = Admin::factory()->create();
        $otherAdmin = Admin::factory()->create();

        foreach (range(1, 6) as $index) {
            $notification = AdminNotification::query()->create([
                'type' => 'other',
                'title' => "Notification {$index}",
                'body' => 'A notification.',
            ]);
            $notification->admins()->attach($admin->id, ['is_read' => false]);
        }

        $unassignedNotification = AdminNotification::query()->create([
            'type' => 'other',
            'title' => 'Unassigned Notification',
            'body' => 'Not assigned to either admin.',
        ]);
        $unassignedNotification->admins()->attach($otherAdmin->id, ['is_read' => false]);

        $this->actingAs($admin, 'web')
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('totalAdminNotifications', 6));
    }

    public function test_admin_cannot_mark_another_admins_notification_read(): void
    {
        $owner = Admin::factory()->create();
        $otherAdmin = Admin::factory()->create();
        $notification = AdminNotification::query()->create([
            'type' => 'request',
            'title' => 'Private Request',
            'body' => 'A request was submitted.',
        ]);
        $notification->admins()->attach($owner->id, ['is_read' => false]);
        $this->actingAs($otherAdmin, 'web')
            ->putJson("/dashboard/notifications/{$notification->id}/read")
            ->assertNotFound();
    }
}
