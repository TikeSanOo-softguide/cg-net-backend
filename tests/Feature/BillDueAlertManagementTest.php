<?php

namespace Tests\Feature;

use App\Enums\NotificationActionType;
use App\Enums\NotificationCategory;
use App\Jobs\SendFtthBillDueNotificationJob;
use App\Models\Admin;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BillDueAlertManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_date_is_seven_days_ahead_and_missing_alerts_are_shown_first(): void
    {
        $admin = $this->notificationAdmin(canCreate: true);
        $user = User::factory()->create(['broadband_account_number' => 'ACCOUNT-123']);
        $dueDate = now()->addDays(7)->toDateString();
        DeviceToken::query()->create(['user_id' => $user->id, 'token' => 'token']);
        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'category' => NotificationCategory::Announcement,
            'action_type' => NotificationActionType::FtthBill->value,
            'action_id' => 'ACCOUNT-123:bill-456',
            'template_data' => [
                'account_number' => 'ACCOUNT-123',
                'due_date' => $dueDate,
            ],
            'sent_at' => null,
        ]);
        Notification::factory()->create([
            'user_id' => $user->id,
            'category' => NotificationCategory::Announcement,
            'action_type' => NotificationActionType::FtthBill->value,
            'action_id' => 'ACCOUNT-123:bill-789',
            'template_data' => [
                'account_number' => 'ACCOUNT-123',
                'due_date' => $dueDate,
            ],
            'sent_at' => now(),
        ]);
        Http::fake([
            'billing-server.test/due-bills*' => Http::response([
                'data' => [
                    [
                        'account_number' => 'ACCOUNT-123',
                        'bill_id' => 'bill-456',
                        'due_date' => $dueDate,
                    ],
                    [
                        'account_number' => 'ACCOUNT-123',
                        'bill_id' => 'bill-789',
                        'due_date' => $dueDate,
                    ],
                    [
                        'account_number' => 'ACCOUNT-123',
                        'bill_id' => 'bill-missing',
                        'due_date' => $dueDate,
                    ],
                ],
            ]),
        ]);

        $this->actingAs($admin, 'web')
            ->get('/notifications/bill-due-alerts?search=ACCOUNT-123')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Notification/BillDueAlerts/Index')
                    ->where('filters.search', 'ACCOUNT-123')
                    ->where('filters.due_date', $dueDate)
                    ->where('alerts.total', 3)
                    ->where('alerts.data.0.action_id', 'ACCOUNT-123:bill-missing')
                    ->where('alerts.data.0.notification_id', null)
                    ->where('alerts.data.0.state', 'not_recorded')
                    ->where('alerts.data.1.customer_name', $user->name)
                    ->where('alerts.data.1.action_id', 'ACCOUNT-123:bill-456')
                    ->where('alerts.data.1.notification_id', $notification->id)
                    ->where('alerts.data.1.state', 'unsent')
                    ->where('alerts.data.2.action_id', 'ACCOUNT-123:bill-789')
                    ->where('alerts.data.2.state', 'sent')
                    ->where('alerts.data.0.has_device', true)
            );

        Http::assertSent(fn (Request $request): bool => str_ends_with(
            parse_url($request->url(), PHP_URL_PATH),
            '/due-bills',
        ) && $request['due_date_from'] === $dueDate && $request['due_date_to'] === $dueDate);
    }

    public function test_due_date_filter_queries_and_compares_bills_for_only_the_selected_day(): void
    {
        $admin = $this->notificationAdmin(canCreate: true);
        $user = User::factory()->create(['broadband_account_number' => 'ACCOUNT-123']);
        $selectedDate = now()->addDays(3)->toDateString();
        Http::fake([
            'billing-server.test/due-bills*' => Http::response([
                'data' => [
                    [
                        'account_number' => 'ACCOUNT-123',
                        'bill_id' => 'bill-selected',
                        'due_date' => $selectedDate,
                    ],
                    [
                        'account_number' => 'ACCOUNT-123',
                        'bill_id' => 'bill-other-day',
                        'due_date' => now()->addDays(4)->toDateString(),
                    ],
                ],
            ]),
        ]);

        $this->actingAs($admin, 'web')
            ->get("/notifications/bill-due-alerts?due_date={$selectedDate}")
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Notification/BillDueAlerts/Index')
                    ->where('filters.due_date', $selectedDate)
                    ->where('alerts.total', 1)
                    ->where('alerts.data.0.user_id', $user->id)
                    ->where('alerts.data.0.action_id', 'ACCOUNT-123:bill-selected')
                    ->where('alerts.data.0.state', 'not_recorded'),
            );

        Http::assertSent(fn (Request $request): bool => str_ends_with(
            parse_url($request->url(), PHP_URL_PATH),
            '/due-bills',
        ) && $request['due_date_from'] === $selectedDate && $request['due_date_to'] === $selectedDate);
    }

    public function test_table_search_filters_billing_api_results_by_customer_or_account(): void
    {
        $admin = $this->notificationAdmin(canCreate: true);
        $matchingUser = User::factory()->create([
            'name' => 'Matching Customer',
            'broadband_account_number' => 'ACCOUNT-123',
        ]);
        User::factory()->create([
            'name' => 'Another Customer',
            'broadband_account_number' => 'ACCOUNT-456',
        ]);
        $dueDate = now()->addDays(2)->toDateString();
        Http::fake([
            'billing-server.test/due-bills*' => Http::response([
                'data' => [
                    [
                        'account_number' => 'ACCOUNT-123',
                        'bill_id' => 'bill-123',
                        'due_date' => $dueDate,
                    ],
                    [
                        'account_number' => 'ACCOUNT-456',
                        'bill_id' => 'bill-456',
                        'due_date' => $dueDate,
                    ],
                ],
            ]),
        ]);

        $this->actingAs($admin, 'web')
            ->get("/notifications/bill-due-alerts?search=matching&due_date={$dueDate}")
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Notification/BillDueAlerts/Index')
                    ->where('filters.search', 'matching')
                    ->where('filters.due_date', $dueDate)
                    ->where('alerts.total', 1)
                    ->where('alerts.data.0.user_id', $matchingUser->id)
                    ->where('alerts.data.0.account_number', 'ACCOUNT-123'),
            );
    }

    public function test_admin_can_retry_an_unsent_bill_alert_from_the_billing_server_list(): void
    {
        $admin = $this->notificationAdmin(canCreate: true);
        $user = User::factory()->create(['broadband_account_number' => 'ACCOUNT-123']);
        DeviceToken::query()->create(['user_id' => $user->id, 'token' => 'token']);
        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'category' => NotificationCategory::Announcement,
            'action_type' => NotificationActionType::FtthBill->value,
            'action_id' => 'ACCOUNT-123:bill-456',
            'template_data' => [
                'account_number' => 'ACCOUNT-123',
                'due_date' => now()->addDays(2)->toDateString(),
            ],
            'sent_at' => null,
        ]);
        Http::fake([
            'billing-server.test/due-bills*' => Http::response([
                'data' => [
                    [
                        'account_number' => 'ACCOUNT-123',
                        'bill_id' => 'bill-456',
                        'due_date' => now()->addDays(2)->toDateString(),
                    ],
                ],
            ]),
        ]);
        Queue::fake();

        $this->actingAs($admin, 'web')
            ->post('/notifications/bill-due-alerts/send', [
                'user_id' => $user->id,
                'account_number' => 'ACCOUNT-123',
                'due_date' => now()->addDays(2)->toDateString(),
                'action_id' => 'ACCOUNT-123:bill-456',
            ])
            ->assertAccepted()
            ->assertJsonPath('message', 'Bill due alert queued for delivery.');

        Queue::assertPushed(
            SendFtthBillDueNotificationJob::class,
            fn (SendFtthBillDueNotificationJob $job): bool => $job->userId === $user->id &&
                $job->accountNumber === 'ACCOUNT-123' &&
                $job->actionId === 'ACCOUNT-123:bill-456',
        );
    }

    public function test_manual_send_only_queues_a_bill_returned_by_the_billing_server_for_an_active_account(): void
    {
        $admin = $this->notificationAdmin(canCreate: true);
        $user = User::factory()->create(['broadband_account_number' => 'ACCOUNT-123']);
        Http::fake([
            'billing-server.test/due-bills*' => Http::response([
                'data' => [
                    [
                        'account_number' => 'ACCOUNT-123',
                        'bill_id' => 'bill-456',
                        'due_date' => now()->addDays(2)->toDateString(),
                    ],
                ],
            ]),
        ]);
        Queue::fake();

        $this->actingAs($admin, 'web')
            ->post('/notifications/bill-due-alerts/send', [
                'user_id' => $user->id,
                'account_number' => 'ACCOUNT-123',
                'due_date' => now()->addDays(2)->toDateString(),
                'action_id' => 'ACCOUNT-123:bill-456',
            ])
            ->assertAccepted()
            ->assertJsonPath('message', 'Bill due alert queued for delivery.');

        Queue::assertPushed(
            SendFtthBillDueNotificationJob::class,
            fn (SendFtthBillDueNotificationJob $job): bool => $job->userId === $user->id &&
                $job->actionId === 'ACCOUNT-123:bill-456',
        );
    }

    public function test_manual_send_does_not_queue_a_bill_that_is_no_longer_due(): void
    {
        $admin = $this->notificationAdmin(canCreate: true);
        $user = User::factory()->create(['broadband_account_number' => 'ACCOUNT-123']);
        Http::fake([
            'billing-server.test/due-bills*' => Http::response(['data' => []]),
        ]);
        Queue::fake();

        $this->actingAs($admin, 'web')
            ->post('/notifications/bill-due-alerts/send', [
                'user_id' => $user->id,
                'account_number' => 'ACCOUNT-123',
                'due_date' => now()->addDays(2)->toDateString(),
                'action_id' => 'ACCOUNT-123:bill-456',
            ])
            ->assertUnprocessable();

        Queue::assertNothingPushed();
    }

    public function test_manual_send_does_not_queue_a_bill_alert_that_was_already_sent(): void
    {
        $admin = $this->notificationAdmin(canCreate: true);
        $user = User::factory()->create(['broadband_account_number' => 'ACCOUNT-123']);
        Notification::factory()->create([
            'user_id' => $user->id,
            'category' => NotificationCategory::Announcement,
            'action_type' => NotificationActionType::FtthBill->value,
            'action_id' => 'ACCOUNT-123:bill-456',
            'sent_at' => now(),
        ]);
        Http::fake([
            'billing-server.test/due-bills*' => Http::response([
                'data' => [
                    [
                        'account_number' => 'ACCOUNT-123',
                        'bill_id' => 'bill-456',
                        'due_date' => now()->addDays(2)->toDateString(),
                    ],
                ],
            ]),
        ]);
        Queue::fake();

        $this->actingAs($admin, 'web')
            ->post('/notifications/bill-due-alerts/send', [
                'user_id' => $user->id,
                'account_number' => 'ACCOUNT-123',
                'due_date' => now()->addDays(2)->toDateString(),
                'action_id' => 'ACCOUNT-123:bill-456',
            ])
            ->assertUnprocessable();

        Queue::assertNothingPushed();
    }

    public function test_notification_view_permission_does_not_allow_manual_resend(): void
    {
        $admin = $this->notificationAdmin(canCreate: false);
        Queue::fake();

        $this->actingAs($admin, 'web')->post('/notifications/bill-due-alerts/send', [])->assertRedirect();

        Queue::assertNothingPushed();
    }

    private function notificationAdmin(bool $canCreate): Admin
    {
        $permissions = [Permission::findOrCreate('notifications.view', 'web')];

        if ($canCreate) {
            $permissions[] = Permission::findOrCreate('notifications.create', 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $role = Role::findOrCreate('Bill Due Alert Manager '.($canCreate ? 'Creator' : 'Viewer'), 'web');
        $role->syncPermissions($permissions);
        $admin = Admin::factory()->create();
        $admin->assignRole($role);

        return $admin;
    }
}
