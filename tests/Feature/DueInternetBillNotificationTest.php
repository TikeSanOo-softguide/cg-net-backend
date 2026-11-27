<?php

namespace Tests\Feature;

use App\Enums\AnnouncementType;
use App\Enums\NotificationCategory;
use App\Jobs\SendInternetBillDueNotificationJob;
use App\Models\Announcement;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\Promotion;
use App\Models\User;
use App\Enums\NotificationTemplateType;
use App\Notifications\InternetBillDueNotification;
use App\Services\Notification\DueInternetBillNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DueInternetBillNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        NotificationTemplate::factory()->create([
            'type' => NotificationTemplateType::BillAlert,
        ]);
    }

    public function test_scan_queues_notifications_for_active_accounts_returned_by_the_due_account_list_api(): void
    {
        $firstUser = User::factory()->create(['broadband_account_number' => 'DUE-ACCOUNT']);
        $unlistedUser = User::factory()->create(['broadband_account_number' => 'UNLISTED-ACCOUNT']);
        $endTime = $this->dueEndTime();
        User::factory()
            ->suspended()
            ->create(['broadband_account_number' => 'DUE-SUSPENDED']);

        $this->fakeDueAccountList(['DUE-ACCOUNT', 'DUE-ACCOUNT', 'DUE-SUSPENDED']);
        Queue::fake();

        $count = app(DueInternetBillNotificationService::class)->dispatchDueBills();

        $this->assertSame(1, $count);
        Queue::assertPushed(SendInternetBillDueNotificationJob::class, 1);
        Queue::assertPushed(
            SendInternetBillDueNotificationJob::class,
            fn(SendInternetBillDueNotificationJob $job): bool => $job->userId === $firstUser->id &&
                $job->actionId === "bill_due:DUE-ACCOUNT:{$endTime}" &&
                $job->endTime === $endTime,
        );
        Queue::assertNotPushed(
            SendInternetBillDueNotificationJob::class,
            fn(SendInternetBillDueNotificationJob $job): bool => $job->userId === $unlistedUser->id,
        );
        Http::assertSent(function (Request $request): bool {
            return str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/DueAccountListAPI') &&
                $request->method() === 'POST' &&
                $request->data() === [];
        });
    }

    public function test_scan_never_queues_an_account_alert_that_was_already_sent(): void
    {
        $user = User::factory()->create(['broadband_account_number' => 'DUE-ACCOUNT']);
        $endTime = $this->dueEndTime();
        Notification::factory()->create([
            'user_id' => $user->id,
            'action_type' => 'ftth_bill',
            'action_id' => "bill_due:DUE-ACCOUNT:{$endTime}",
            'template_data' => [
                'account_number' => 'DUE-ACCOUNT',
                'endtime' => $endTime,
                'due_date' => now()->addDays(3)->toDateString(),
            ],
            'sent_at' => now(),
        ]);
        $this->fakeDueAccountList(['DUE-ACCOUNT']);
        Queue::fake();

        $this->assertSame(0, app(DueInternetBillNotificationService::class)->dispatchDueBills());
        Queue::assertNothingPushed();
    }

    public function test_scan_queues_an_account_again_when_its_endtime_changes(): void
    {
        $user = User::factory()->create(['broadband_account_number' => 'DUE-ACCOUNT']);
        $previousEndTime = now()->addDays(3)->setTime(16, 37)->format('Y-m-d H:i');
        $currentEndTime = now()->addDays(3)->setTime(17, 15)->format('Y-m-d H:i');
        Notification::factory()->create([
            'user_id' => $user->id,
            'action_type' => 'ftth_bill',
            'action_id' => "bill_due:DUE-ACCOUNT:{$previousEndTime}",
            'template_data' => [
                'account_number' => 'DUE-ACCOUNT',
                'endtime' => $previousEndTime,
                'due_date' => substr($previousEndTime, 0, 10),
            ],
            'sent_at' => now(),
        ]);
        $this->fakeDueAccountList([['account' => 'DUE-ACCOUNT', 'endtime' => $currentEndTime]]);
        Queue::fake();

        $this->assertSame(1, app(DueInternetBillNotificationService::class)->dispatchDueBills());
        Queue::assertPushed(
            SendInternetBillDueNotificationJob::class,
            fn(SendInternetBillDueNotificationJob $job): bool => $job->userId === $user->id &&
                $job->endTime === $currentEndTime &&
                $job->actionId === "bill_due:DUE-ACCOUNT:{$currentEndTime}",
        );
    }

    public function test_due_account_list_rejects_multiple_endtimes_for_one_account(): void
    {
        $this->fakeDueAccountList([
            ['account' => 'DUE-ACCOUNT', 'endtime' => $this->dueEndTime()],
            [
                'account' => 'DUE-ACCOUNT',
                'endtime' => now()->addDays(4)->setTime(16, 37)->format('Y-m-d H:i'),
            ],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The CG API returned multiple due times for one account.');

        app(DueInternetBillNotificationService::class)->dueBillAlerts();
    }

    public function test_due_account_endtimes_keep_minute_precision(): void
    {
        User::factory()->create(['broadband_account_number' => 'DUE-ACCOUNT']);
        $this->fakeDueAccountList([
            ['account' => 'DUE-ACCOUNT', 'endtime' => '2026-11-29 08:43'],
            ['account' => 'DUE-ACCOUNT', 'endtime' => '2026-11-29 08:43:15'],
        ]);

        $alert = app(DueInternetBillNotificationService::class)->dueBillAlerts()[0];

        $this->assertSame('2026-11-29 08:43', $alert['endtime']);
        $this->assertSame('bill_due:DUE-ACCOUNT:2026-11-29 08:43', $alert['action_id']);
        $this->assertArrayNotHasKey('due_date', $alert);
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

    public function test_delivery_creates_a_common_notification_record_and_sends_fcm_once(): void
    {
        $user = User::factory()->create(['broadband_account_number' => 'CG12345678', 'lang' => 'zh']);
        DeviceToken::query()->create(['user_id' => $user->id, 'token' => 'device-token']);
        NotificationFacade::fake();

        $job = new SendInternetBillDueNotificationJob(
            userId: (int) $user->id,
            accountNumber: 'CG12345678',
            endTime: '2026-10-11 16:37',
            actionId: 'bill-123',
        );

        $job->handle();
        $job->handle();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'category' => NotificationCategory::Announcement->value,
            'action_type' => 'ftth_bill',
            'action_id' => 'bill-123',
            'is_read' => false,
        ]);
        $stored = Notification::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('bill_alert', $stored->templateable->type->value);
        $this->assertSame('CG12345678', $stored->template_data['account_number']);
        $this->assertSame('2026-10-11 16:37', $stored->template_data['endtime']);
        $this->assertArrayNotHasKey('due_date', $stored->template_data);
        $this->assertStringContainsString(
            'October 11, 2026 4:37 PM',
            $stored->renderedContent('zh')['description'],
        );
        $this->assertSame(1, Notification::query()->where('user_id', $user->id)->count());
        NotificationFacade::assertSentTo($user, InternetBillDueNotification::class, 1);
        NotificationFacade::assertSentTo(
            $user,
            InternetBillDueNotification::class,
            fn(InternetBillDueNotification $sent): bool => $sent->title === '互联网账户即将到期' &&
                str_contains($sent->body, "October 11, 2026 4:37 PM 到期。\n") &&
                str_contains($sent->body, 'October 11, 2026 4:37 PM'),
        );
    }

    public function test_delivery_still_stores_a_notification_when_the_user_has_no_device_token(): void
    {
        $user = User::factory()->create(['broadband_account_number' => 'CG12345678']);
        NotificationFacade::fake();

        (new SendInternetBillDueNotificationJob(
            userId: (int) $user->id,
            accountNumber: 'CG12345678',
            endTime: '2026-10-11 16:37',
            actionId: 'bill-without-device',
        ))->handle();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'action_type' => 'ftth_bill',
            'action_id' => 'bill-without-device',
        ]);
        $this->assertNull(Notification::query()->where('action_id', 'bill-without-device')->value('sent_at'));
        NotificationFacade::assertNothingSent();
    }

    public function test_delivery_retries_an_existing_notification_after_a_device_token_is_registered(): void
    {
        $user = User::factory()->create(['broadband_account_number' => 'CG12345678']);
        NotificationFacade::fake();
        $job = new SendInternetBillDueNotificationJob(
            userId: (int) $user->id,
            accountNumber: 'CG12345678',
            endTime: '2026-10-11 16:37',
            actionId: 'bill-token-added-later',
        );

        $job->handle();
        DeviceToken::query()->create(['user_id' => $user->id, 'token' => 'registered-later']);
        (new SendInternetBillDueNotificationJob(
            userId: (int) $user->id,
            accountNumber: 'CG12345678',
            endTime: '2026-10-11 16:37',
            actionId: 'bill-token-added-later',
        ))->handle();

        $notification = Notification::query()->where('action_id', 'bill-token-added-later')->firstOrFail();
        $this->assertNotNull($notification->sent_at);
        $this->assertSame('2026-10-11 16:37', $notification->template_data['endtime']);
        NotificationFacade::assertSentTo($user, InternetBillDueNotification::class, 1);
    }

    public function test_notification_endpoints_are_scoped_to_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'category' => NotificationCategory::Announcement,
            'action_type' => 'ftth_bill',
            'action_id' => 'bill-123',
            'is_read' => false,
            'template_data' => [
                'account_number' => '1234',
                'endtime' => '2026-11-20 17:30',
            ],
        ]);
        $otherNotification = Notification::factory()->create([
            'user_id' => $otherUser->id,
            'is_read' => false,
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token, 'Bearer')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.category', 'announcement')
            ->assertJsonPath('data.0.action_type', 'ftth_bill')
            ->assertJsonPath('data.0.action_id', 'bill-123')
            ->assertJsonPath('data.0.title', 'Internet account expiring soon')
            ->assertJsonPath(
                'data.0.body',
                "Account 1234 expires at November 20, 2026 5:30 PM.\nRecharge to stay connected.",
            )
            ->assertJsonPath('data.0.is_read', false);

        $this->withToken($token, 'Bearer')
            ->patchJson("/api/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->withToken($token, 'Bearer')
            ->patchJson("/api/notifications/{$otherNotification->id}/read")
            ->assertNotFound();

        $this->assertDatabaseHas('notifications', ['id' => $notification->id, 'is_read' => true]);
        $this->assertFalse((bool) Notification::query()->findOrFail($otherNotification->id)->is_read);
    }

    public function test_notification_endpoint_prioritizes_unread_notifications(): void
    {
        $user = User::factory()->create();
        $template = NotificationTemplate::query()->firstOrFail();
        $unread = Notification::factory()->create([
            'user_id' => $user->id,
            'templateable_type' => NotificationTemplate::class,
            'templateable_id' => $template->id,
            'is_read' => false,
            'created_at' => now()->subDay(),
        ]);

        foreach (range(1, 30) as $index) {
            Notification::factory()->create([
                'user_id' => $user->id,
                'templateable_type' => NotificationTemplate::class,
                'templateable_id' => $template->id,
                'is_read' => true,
                'created_at' => now()->addSeconds($index),
            ]);
        }

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.id', $unread->id)
            ->assertJsonPath('data.0.is_read', false)
            ->assertJsonPath('unread_count', 1);
    }

    public function test_deleted_announcements_and_promotions_stay_out_of_the_inbox(): void
    {
        $user = User::factory()->create();
        $announcement = Announcement::factory()->create();
        $promotion = Promotion::factory()->create();
        $kept = Notification::factory()->create([
            'user_id' => $user->id,
            'category' => NotificationCategory::BillAlert,
            'is_read' => false,
            'action_type' => 'ftth_bill',
            'action_id' => 'bill-kept',
        ]);
        Notification::factory()->create([
            'user_id' => $user->id,
            'category' => NotificationCategory::Announcement,
            'is_read' => false,
            'action_type' => 'announcement',
            'action_id' => (string) $announcement->id,
            'templateable_type' => Announcement::class,
            'templateable_id' => $announcement->id,
            'template_data' => [
                'title_en' => 'Deleted announcement',
                'body_en' => 'This announcement was removed.',
            ],
        ]);
        Notification::factory()->create([
            'user_id' => $user->id,
            'category' => NotificationCategory::Promotion,
            'is_read' => false,
            'action_type' => 'promotion',
            'action_id' => (string) $promotion->id,
            'templateable_type' => Promotion::class,
            'templateable_id' => $promotion->id,
            'template_data' => [
                'title_en' => 'Deleted promotion',
                'body_en' => 'This promotion was removed.',
            ],
        ]);
        $announcement->delete();
        $promotion->delete();

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $kept->id);
    }

    public function test_a_new_system_announcement_appears_in_the_inbox_before_it_is_pushed(): void
    {
        $user = User::factory()->create(['lang' => 'en']);
        $announcement = Announcement::factory()->create([
            'type' => AnnouncementType::System,
            'title_en' => 'System notice',
            'content_en' => 'Network work tonight.',
            'is_active' => true,
            'start_date' => now()->subHour(),
            'end_date' => now()->addDay(),
        ]);
        Announcement::factory()
            ->inactive()
            ->create([
                'type' => AnnouncementType::System,
                'title_en' => 'Hidden notice',
            ]);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'System notice',
                'body' => 'Network work tonight.',
                'category' => 'system',
                'action_type' => 'announcement',
                'action_id' => (string) $announcement->id,
                'is_read' => false,
            ])
            ->assertJsonMissing(['title' => 'Hidden notice']);
    }

    public function test_fcm_data_contains_action_metadata_for_deep_linking(): void
    {
        $user = User::factory()->create();
        $notification = new InternetBillDueNotification(
            title: 'Internet account expiring soon',
            body: 'Account CG12345678 expires soon. Recharge to stay connected.',
            actionId: 'bill-123',
        );

        $message = $notification->toFcm($user)->toArray();

        $this->assertSame('ftth_bill', $message['data']['action_type']);
        $this->assertSame('bill-123', $message['data']['action_id']);
        $this->assertSame('Internet account expiring soon', $message['webpush']['notification']['title']);
        $this->assertSame('user_notification', $message['webpush']['notification']['data']['type']);
        $this->assertSame('bill-123', $message['webpush']['notification']['data']['action_id']);
    }
}
