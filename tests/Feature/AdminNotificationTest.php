<?php

namespace Tests\Feature;

use App\Enums\ChatConversationStatus;
use App\Enums\LedgerHealthScanType;
use App\Events\AdminNotificationCreated;
use App\Events\LiveChatRequested;
use App\Events\ServiceRequestSubmitted;
use App\Listeners\CreateAdminNotification;
use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\ChatConversation;
use App\Models\ChangePasswordRequest;
use App\Models\LedgerHealthSnapshot;
use App\Models\User;
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

    public function test_live_chat_request_creates_bell_notification_for_support_admins(): void
    {
        RolePermissionSeeder::sync();
        Event::fake([AdminNotificationCreated::class]);

        $supportAdmin = Admin::factory()->create();
        $supportAdmin->givePermissionTo(['notifications.view', 'support.view']);

        $unrelatedAdmin = Admin::factory()->create();
        $unrelatedAdmin->givePermissionTo(['notifications.view', 'service-requests.view']);

        $user = User::factory()->create();
        $conversation = ChatConversation::query()->create([
            'user_id' => $user->id,
            'status' => ChatConversationStatus::WaitingAgent,
        ]);

        LiveChatRequested::dispatch($conversation);

        $notification = AdminNotification::query()->sole();
        $this->assertSame('Live Chat Requested', $notification->title);
        $this->assertSame('A customer is requesting to chat with a staff member.', $notification->body);
        $this->assertSame('chat_conversation', $notification->reference_type);
        $this->assertSame($conversation->id, $notification->reference_id);
        $this->assertSame(
            '/support/conversations?conversation='.$conversation->id,
            $notification->toDropdownArray()['href'],
        );
        $this->assertSame([$supportAdmin->id], $notification->admins()->pluck('admins.id')->all());
        $this->assertDatabaseHas('admin_notification_recipients', [
            'admin_notification_id' => $notification->id,
            'admin_id' => $supportAdmin->id,
            'is_read' => false,
        ]);
        Event::assertDispatched(
            AdminNotificationCreated::class,
            fn (AdminNotificationCreated $event): bool => $event->admin->is($supportAdmin),
        );
    }

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

    public function test_opening_a_request_marks_its_notification_read_for_the_current_admin_only(): void
    {
        RolePermissionSeeder::sync();
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['notifications.view', 'service-requests.view']);
        $otherAdmin = Admin::factory()->create();
        $notification = AdminNotification::query()->create([
            'type' => 'request',
            'title' => 'New Change Password Request',
            'body' => 'A request was submitted.',
            'reference_type' => 'change_password_request',
            'reference_id' => 42,
        ]);
        $notification->admins()->attach([
            $admin->id => ['is_read' => false, 'read_at' => now()],
            $otherAdmin->id => ['is_read' => false],
        ]);

        $this->actingAs($admin, 'web')
            ->putJson('/dashboard/notifications/reference/change_password_request/42/read')
            ->assertOk()
            ->assertJsonPath('marked_read', 1);

        $this->assertDatabaseHas('admin_notification_recipients', [
            'admin_notification_id' => $notification->id,
            'admin_id' => $admin->id,
            'is_read' => true,
        ]);
        $this->assertDatabaseHas('admin_notification_recipients', [
            'admin_notification_id' => $notification->id,
            'admin_id' => $otherAdmin->id,
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    public function test_opening_a_package_order_marks_its_notification_read_for_current_billing_admin(): void
    {
        RolePermissionSeeder::sync();
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['notifications.view', 'billing.view']);
        $otherAdmin = Admin::factory()->create();
        $notification = AdminNotification::query()->create([
            'type' => 'other',
            'title' => 'Package order requires manual review',
            'body' => 'A package order needs attention.',
            'reference_type' => 'package_order',
            'reference_id' => 42,
        ]);
        $notification->admins()->attach([
            $admin->id => ['is_read' => false],
            $otherAdmin->id => ['is_read' => false],
        ]);

        $this->actingAs($admin, 'web')
            ->putJson('/dashboard/notifications/reference/package_order/42/read')
            ->assertOk()
            ->assertJsonPath('marked_read', 1);

        $this->assertDatabaseHas('admin_notification_recipients', [
            'admin_notification_id' => $notification->id,
            'admin_id' => $admin->id,
            'is_read' => true,
        ]);
        $this->assertDatabaseHas('admin_notification_recipients', [
            'admin_notification_id' => $notification->id,
            'admin_id' => $otherAdmin->id,
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    public function test_opening_a_ledger_snapshot_marks_its_notification_read_for_the_current_admin_only(): void
    {
        RolePermissionSeeder::sync();
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['notifications.view', 'reports.view']);
        $otherAdmin = Admin::factory()->create();
        $notification = AdminNotification::query()->create([
            'type' => 'other',
            'title' => 'Ledger Health Check Completed',
            'body' => 'The Daily ledger health check completed successfully.',
            'reference_type' => 'ledger_health_snapshot_daily',
            'reference_id' => 42,
        ]);
        $notification->admins()->attach([
            $admin->id => ['is_read' => false],
            $otherAdmin->id => ['is_read' => false],
        ]);

        $this->actingAs($admin, 'web')
            ->putJson('/dashboard/notifications/reference/ledger_health_snapshot_daily/42/read')
            ->assertOk()
            ->assertJsonPath('marked_read', 1);

        $this->assertDatabaseHas('admin_notification_recipients', [
            'admin_notification_id' => $notification->id,
            'admin_id' => $admin->id,
            'is_read' => true,
        ]);
        $this->assertDatabaseHas('admin_notification_recipients', [
            'admin_notification_id' => $notification->id,
            'admin_id' => $otherAdmin->id,
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    public function test_service_request_and_ledger_snapshot_lists_expose_unread_status(): void
    {
        RolePermissionSeeder::sync();
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(['service-requests.view', 'reports.view']);

        $request = ChangePasswordRequest::factory()->create();
        $snapshot = LedgerHealthSnapshot::query()->create([
            'requested_by' => $admin->id,
            'type' => LedgerHealthScanType::Daily,
            'status' => 'completed',
            'results' => [],
            'checked_at' => now(),
        ]);

        $requestNotification = AdminNotification::query()->create([
            'type' => 'request',
            'title' => 'New Change Password Request',
            'body' => 'A request was submitted.',
            'reference_type' => 'change_password_request',
            'reference_id' => $request->id,
        ]);
        $ledgerNotification = AdminNotification::query()->create([
            'type' => 'other',
            'title' => 'Ledger Health Check Completed',
            'body' => 'The Daily ledger health check completed successfully.',
            'reference_type' => 'ledger_health_snapshot_daily',
            'reference_id' => $snapshot->id,
        ]);
        $requestNotification->admins()->attach($admin->id, ['is_read' => false]);
        $ledgerNotification->admins()->attach($admin->id, ['is_read' => false]);

        $this->actingAs($admin, 'web')
            ->get('/service-requests/change-password')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('requests.data.0.notification_unread', true));

        $this->actingAs($admin, 'web')
            ->get('/reports/ledger-health?type=daily')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('snapshots.0.id', $snapshot->id)
                    ->where('snapshots.0.notification_unread', true),
            );
    }
}
