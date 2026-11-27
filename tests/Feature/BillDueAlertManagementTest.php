<?php

namespace Tests\Feature;

use App\Enums\NotificationActionType;
use App\Enums\NotificationCategory;
use App\Jobs\SendInternetBillDueNotificationJob;
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

    public function test_api_due_accounts_are_compared_with_notification_records_and_missing_alerts_are_shown_first(): void
    {
        $admin = $this->notificationAdmin(canCreate: true);
        $missingUser = User::factory()->create(['broadband_account_number' => 'ACCOUNT-MISSING']);
        $unsentUser = User::factory()->create(['broadband_account_number' => 'ACCOUNT-UNSENT']);
        $sentUser = User::factory()->create(['broadband_account_number' => 'ACCOUNT-SENT']);
        $endTime = $this->dueEndTime();
        $dueDate = now()->addDays(3)->toDateString();
        DeviceToken::query()->create(['user_id' => $missingUser->id, 'token' => 'token']);
        $unsentNotification = Notification::factory()->create([
            'user_id' => $unsentUser->id,
            'category' => NotificationCategory::Announcement,
            'action_type' => NotificationActionType::FtthBill->value,
            'action_id' => "bill_due:ACCOUNT-UNSENT:{$endTime}",
            'template_data' => [
                'account_number' => 'ACCOUNT-UNSENT',
                'endtime' => $endTime,
                'due_date' => $dueDate,
            ],
            'sent_at' => null,
        ]);
        Notification::factory()->create([
            'user_id' => $sentUser->id,
            'category' => NotificationCategory::Announcement,
            'action_type' => NotificationActionType::FtthBill->value,
            'action_id' => "bill_due:ACCOUNT-SENT:{$endTime}",
            'template_data' => [
                'account_number' => 'ACCOUNT-SENT',
                'endtime' => $endTime,
                'due_date' => $dueDate,
            ],
            'sent_at' => now(),
        ]);
        $this->fakeDueAccountList(['ACCOUNT-MISSING', 'ACCOUNT-UNSENT', 'ACCOUNT-SENT']);

        $this->actingAs($admin, 'web')
            ->get('/notifications/bill-due-alerts?search=ACCOUNT-')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Notification/BillDueAlerts/Index')
                    ->where('filters.search', 'ACCOUNT-')
                    ->where('alerts.total', 3)
                    ->where('alerts.data.0.action_id', "bill_due:ACCOUNT-MISSING:{$endTime}")
                    ->where('alerts.data.0.endtime', $endTime)
                    ->where('alerts.data.0.notification_id', null)
                    ->where('alerts.data.0.state', 'not_recorded')
                    ->where('alerts.data.1.customer_name', $unsentUser->name)
                    ->where('alerts.data.1.action_id', "bill_due:ACCOUNT-UNSENT:{$endTime}")
                    ->where('alerts.data.1.notification_id', $unsentNotification->id)
                    ->where('alerts.data.1.state', 'unsent')
                    ->where('alerts.data.2.action_id', "bill_due:ACCOUNT-SENT:{$endTime}")
                    ->where('alerts.data.2.state', 'sent')
                    ->where('alerts.data.0.has_device', true),
            );

        Http::assertSent(
            fn(Request $request): bool => str_ends_with(
                parse_url($request->url(), PHP_URL_PATH),
                '/DueAccountListAPI',
            ) &&
                $request->method() === 'POST' &&
                $request->data() === [],
        );
    }

    public function test_screen_shows_an_error_message_when_the_due_account_api_is_unavailable(): void
    {
        $admin = $this->notificationAdmin(canCreate: true);

        config([
            'services.cg_api.url' => 'https://provider.test/WalletApp/ServerCommand',
            'services.cg_api.client_id' => 'test-client',
            'services.cg_api.client_secret' => 'test-secret',
        ]);
        Http::fake([
            'provider.test/WalletApp/ServerCommand/GetTokenAPI*' => Http::response([
                'ErrCode' => 0,
                'token' => 'test-access-token',
                'expires_in' => 3600,
            ]),
            'provider.test/WalletApp/ServerCommand/DueAccountListAPI' => Http::response(
                ['Message' => 'Unavailable'],
                503,
            ),
        ]);

        $this->actingAs($admin, 'web')
            ->get('/notifications/bill-due-alerts')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Notification/BillDueAlerts/Index')
                    ->where('errorKey', 'notification.bill_due_alerts.load_failed')
                    ->where('alerts.total', 0)
                    ->where('alerts.data', []),
            );
    }

    public function test_screen_uses_the_api_due_account_list_without_filtering_by_date(): void
    {
        $admin = $this->notificationAdmin(canCreate: true);
        $user = User::factory()->create(['broadband_account_number' => 'ACCOUNT-123']);
        $endTime = now()->addDays(5)->setTime(16, 37)->format('Y-m-d H:i');
        $this->fakeDueAccountList([
            ['account' => 'ACCOUNT-123', 'endtime' => $endTime],
            ['account' => 'ACCOUNT-456', 'endtime' => $endTime],
        ]);

        $this->actingAs($admin, 'web')->get('/notifications/bill-due-alerts')->assertOk()->assertInertia(
            fn(Assert $page) => $page
                ->component('Notification/BillDueAlerts/Index')
                ->where('alerts.total', 1)
                ->where('alerts.data.0.user_id', $user->id)
                ->where('alerts.data.0.action_id', "bill_due:ACCOUNT-123:{$endTime}")
                ->where('alerts.data.0.endtime', $endTime)
                ->where('alerts.data.0.state', 'not_recorded'),
        );

        Http::assertSent(
            fn(Request $request): bool => str_ends_with(
                parse_url($request->url(), PHP_URL_PATH),
                '/DueAccountListAPI',
            ) &&
                $request->method() === 'POST' &&
                $request->data() === [],
        );
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
        $endTime = $this->dueEndTime();
        $this->fakeDueAccountList(['ACCOUNT-123', 'ACCOUNT-456']);

        $this->actingAs($admin, 'web')
            ->get('/notifications/bill-due-alerts?search=matching')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Notification/BillDueAlerts/Index')
                    ->where('filters.search', 'matching')
                    ->where('alerts.total', 1)
                    ->where('alerts.data.0.user_id', $matchingUser->id)
                    ->where('alerts.data.0.account_number', 'ACCOUNT-123'),
            );
    }

    public function test_admin_can_retry_an_unsent_bill_alert_from_the_due_account_list(): void
    {
        $admin = $this->notificationAdmin(canCreate: true);
        $user = User::factory()->create(['broadband_account_number' => 'ACCOUNT-123']);
        $endTime = $this->dueEndTime();
        DeviceToken::query()->create(['user_id' => $user->id, 'token' => 'token']);
        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'category' => NotificationCategory::Announcement,
            'action_type' => NotificationActionType::FtthBill->value,
            'action_id' => "bill_due:ACCOUNT-123:{$endTime}",
            'template_data' => [
                'account_number' => 'ACCOUNT-123',
                'endtime' => $endTime,
                'due_date' => now()->addDays(3)->toDateString(),
            ],
            'sent_at' => null,
        ]);
        $this->fakeDueAccountList(['ACCOUNT-123']);
        Queue::fake();

        $this->actingAs($admin, 'web')
            ->post('/notifications/bill-due-alerts/send', [
                'user_id' => $user->id,
                'account_number' => 'ACCOUNT-123',
                'endtime' => $endTime,
                'action_id' => "bill_due:ACCOUNT-123:{$endTime}",
            ])
            ->assertAccepted()
            ->assertJsonPath('message', 'Bill due alert queued for delivery.');

        Queue::assertPushed(
            SendInternetBillDueNotificationJob::class,
            fn(SendInternetBillDueNotificationJob $job): bool => $job->userId === $user->id &&
                $job->accountNumber === 'ACCOUNT-123' &&
                $job->endTime === $endTime &&
                $job->actionId === "bill_due:ACCOUNT-123:{$endTime}",
        );
    }

    public function test_manual_send_only_queues_an_account_and_endtime_returned_by_the_due_account_api(): void
    {
        $admin = $this->notificationAdmin(canCreate: true);
        $user = User::factory()->create(['broadband_account_number' => 'ACCOUNT-123']);
        $endTime = $this->dueEndTime();
        $this->fakeDueAccountList(['ACCOUNT-123']);
        Queue::fake();

        $this->actingAs($admin, 'web')
            ->post('/notifications/bill-due-alerts/send', [
                'user_id' => $user->id,
                'account_number' => 'ACCOUNT-123',
                'endtime' => $endTime,
                'action_id' => "bill_due:ACCOUNT-123:{$endTime}",
            ])
            ->assertAccepted()
            ->assertJsonPath('message', 'Bill due alert queued for delivery.');

        Queue::assertPushed(
            SendInternetBillDueNotificationJob::class,
            fn(SendInternetBillDueNotificationJob $job): bool => $job->userId === $user->id &&
                $job->endTime === $endTime &&
                $job->actionId === "bill_due:ACCOUNT-123:{$endTime}",
        );
    }

    public function test_manual_send_does_not_queue_an_account_absent_from_the_bill_list_api(): void
    {
        $admin = $this->notificationAdmin(canCreate: true);
        $user = User::factory()->create(['broadband_account_number' => 'ACCOUNT-123']);
        $endTime = $this->dueEndTime();
        $this->fakeDueAccountList([]);
        Queue::fake();

        $this->actingAs($admin, 'web')
            ->post('/notifications/bill-due-alerts/send', [
                'user_id' => $user->id,
                'account_number' => 'ACCOUNT-123',
                'endtime' => $endTime,
                'action_id' => "bill_due:ACCOUNT-123:{$endTime}",
            ])
            ->assertUnprocessable();

        Queue::assertNothingPushed();
    }

    public function test_manual_send_does_not_queue_a_bill_alert_that_was_already_sent(): void
    {
        $admin = $this->notificationAdmin(canCreate: true);
        $user = User::factory()->create(['broadband_account_number' => 'ACCOUNT-123']);
        $endTime = $this->dueEndTime();
        Notification::factory()->create([
            'user_id' => $user->id,
            'category' => NotificationCategory::Announcement,
            'action_type' => NotificationActionType::FtthBill->value,
            'action_id' => "bill_due:ACCOUNT-123:{$endTime}",
            'template_data' => [
                'account_number' => 'ACCOUNT-123',
                'endtime' => $endTime,
                'due_date' => now()->addDays(3)->toDateString(),
            ],
            'sent_at' => now(),
        ]);
        $this->fakeDueAccountList(['ACCOUNT-123']);
        Queue::fake();

        $this->actingAs($admin, 'web')
            ->post('/notifications/bill-due-alerts/send', [
                'user_id' => $user->id,
                'account_number' => 'ACCOUNT-123',
                'endtime' => $endTime,
                'action_id' => "bill_due:ACCOUNT-123:{$endTime}",
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
        $role = Role::findOrCreate('Bill Due Alert Manager ' . ($canCreate ? 'Creator' : 'Viewer'), 'web');
        $role->syncPermissions($permissions);
        $admin = Admin::factory()->create();
        $admin->assignRole($role);

        return $admin;
    }

    private function dueEndTime(): string
    {
        return now()->addDays(3)->setTime(16, 37)->format('Y-m-d H:i');
    }

    /**
     * @param  list<string|array{account: string, endtime: string}>  $accounts
     */
    private function fakeDueAccountList(array $accounts): void
    {
        config([
            'services.cg_api.url' => 'https://provider.test/WalletApp/ServerCommand',
            'services.cg_api.client_id' => 'test-client',
            'services.cg_api.client_secret' => 'test-secret',
        ]);

        Http::fake([
            'provider.test/WalletApp/ServerCommand/GetTokenAPI*' => Http::response([
                'ErrCode' => 0,
                'token' => 'test-access-token',
                'expires_in' => 3600,
            ]),
            'provider.test/WalletApp/ServerCommand/DueAccountListAPI' => Http::response([
                'ErrCode' => 0,
                'Message' => 'ok',
                'Data' => array_map(
                    fn(string|array $account): array => is_string($account)
                        ? ['account' => $account, 'endtime' => $this->dueEndTime()]
                        : $account,
                    $accounts,
                ),
            ]),
        ]);
    }
}
