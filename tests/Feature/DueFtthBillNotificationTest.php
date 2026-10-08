<?php

namespace Tests\Feature;

use App\Enums\NotificationCategory;
use App\Jobs\SendFtthBillDueNotificationJob;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\User;
use App\Notifications\FtthBillDueNotification;
use App\Services\Notification\DueFtthBillNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DueFtthBillNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_queues_notifications_only_for_active_users_with_bills_due_in_seven_days(): void
    {
        $firstUser = User::factory()->create(['broadband_account_number' => 'DUE-ACCOUNT']);
        $sharedAccountUser = User::factory()->create(['broadband_account_number' => 'DUE-ACCOUNT']);
        $outsideWindowUser = User::factory()->create(['broadband_account_number' => 'LATE-ACCOUNT']);
        User::factory()->suspended()->create(['broadband_account_number' => 'DUE-ACCOUNT']);

        Http::fake([
            'billing-server.test/due-bills*' => Http::response([
                'data' => [
                    [
                        'account_number' => 'DUE-ACCOUNT',
                        'bill_month' => '2026-10',
                        'due_date' => now()->addDays(7)->toDateString(),
                        'bill_id' => 'bill-due',
                    ],
                    [
                        'account_number' => 'LATE-ACCOUNT',
                        'bill_month' => '2026-10',
                        'due_date' => now()->addDays(8)->toDateString(),
                        'bill_id' => 'bill-late',
                    ],
                ],
            ]),
        ]);
        Queue::fake();

        $count = app(DueFtthBillNotificationService::class)->dispatchDueBills();

        $this->assertSame(2, $count);
        Queue::assertPushed(SendFtthBillDueNotificationJob::class, 2);
        Queue::assertPushed(
            SendFtthBillDueNotificationJob::class,
            fn (SendFtthBillDueNotificationJob $job): bool => $job->userId === $firstUser->id,
        );
        Queue::assertPushed(
            SendFtthBillDueNotificationJob::class,
            fn (SendFtthBillDueNotificationJob $job): bool => $job->userId === $sharedAccountUser->id,
        );
        Queue::assertNotPushed(
            SendFtthBillDueNotificationJob::class,
            fn (SendFtthBillDueNotificationJob $job): bool => $job->userId === $outsideWindowUser->id,
        );
        $this->assertCount(1, Http::recorded());
        Http::assertSent(function (Request $request): bool {
            return str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/due-bills')
                && $request['due_date_from'] === now()->toDateString()
                && $request['due_date_to'] === now()->addDays(7)->toDateString();
        });
    }

    public function test_delivery_creates_a_common_notification_record_and_sends_fcm_once(): void
    {
        $user = User::factory()->create(['broadband_account_number' => 'CG12345678', 'lang' => 'zh']);
        DeviceToken::query()->create(['user_id' => $user->id, 'token' => 'device-token']);
        NotificationFacade::fake();

        $job = new SendFtthBillDueNotificationJob(
            userId: (int) $user->id,
            accountNumber: 'CG12345678',
            dueDate: '2026-10-11',
            actionId: 'bill-123',
        );

        $job->handle();
        $job->handle();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'category' => NotificationCategory::BillAlert->value,
            'action_type' => 'ftth_bill',
            'action_id' => 'bill-123',
            'is_read' => false,
        ]);
        $stored = Notification::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('bill_alert', $stored->templateable->type->value);
        $this->assertSame('CG12345678', $stored->template_data['account_number']);
        $this->assertSame(1, Notification::query()->where('user_id', $user->id)->count());
        NotificationFacade::assertSentTo($user, FtthBillDueNotification::class, 1);
        NotificationFacade::assertSentTo(
            $user,
            FtthBillDueNotification::class,
            fn (FtthBillDueNotification $sent): bool => $sent->title === 'FTTH 账单即将到期'
                && str_contains($sent->body, 'CG12345678'),
        );
    }

    public function test_delivery_still_stores_a_notification_when_the_user_has_no_device_token(): void
    {
        $user = User::factory()->create(['broadband_account_number' => 'CG12345678']);
        NotificationFacade::fake();

        (new SendFtthBillDueNotificationJob(
            userId: (int) $user->id,
            accountNumber: 'CG12345678',
            dueDate: '2026-10-11',
            actionId: 'bill-without-device',
        ))->handle();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'action_type' => 'ftth_bill',
            'action_id' => 'bill-without-device',
        ]);
        $this->assertNull(
            Notification::query()->where('action_id', 'bill-without-device')->value('sent_at'),
        );
        NotificationFacade::assertNothingSent();
    }

    public function test_delivery_retries_an_existing_notification_after_a_device_token_is_registered(): void
    {
        $user = User::factory()->create(['broadband_account_number' => 'CG12345678']);
        NotificationFacade::fake();
        $job = new SendFtthBillDueNotificationJob(
            userId: (int) $user->id,
            accountNumber: 'CG12345678',
            dueDate: '2026-10-11',
            actionId: 'bill-token-added-later',
        );

        $job->handle();
        DeviceToken::query()->create(['user_id' => $user->id, 'token' => 'registered-later']);
        $job->handle();

        $this->assertNotNull(
            Notification::query()->where('action_id', 'bill-token-added-later')->value('sent_at'),
        );
        NotificationFacade::assertSentTo($user, FtthBillDueNotification::class, 1);
    }

    public function test_notification_endpoints_are_scoped_to_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'category' => NotificationCategory::BillAlert,
            'action_type' => 'ftth_bill',
            'action_id' => 'bill-123',
            'is_read' => false,
        ]);
        $otherNotification = Notification::factory()->create([
            'user_id' => $otherUser->id,
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token, 'Bearer')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.category', 'bill_alert')
            ->assertJsonPath('data.0.action_type', 'ftth_bill')
            ->assertJsonPath('data.0.action_id', 'bill-123')
            ->assertJsonPath('data.0.title', 'FTTH bill due soon')
            ->assertJsonPath(
                'data.0.body',
                'Your bill for account '.$notification->template_data['account_number'].' is due on '.$notification->template_data['due_date'].'. Tap to view details.',
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
        $this->assertDatabaseHas('notifications', ['id' => $otherNotification->id, 'is_read' => false]);
    }

    public function test_fcm_data_contains_action_metadata_for_deep_linking(): void
    {
        $user = User::factory()->create();
        $notification = new FtthBillDueNotification(
            title: 'FTTH bill due soon',
            body: 'Your bill for account CG12345678 is due soon.',
            actionId: 'bill-123',
        );

        $message = $notification->toFcm($user)->toArray();

        $this->assertSame('ftth_bill', $message['data']['action_type']);
        $this->assertSame('bill-123', $message['data']['action_id']);
        $this->assertSame('FTTH bill due soon', $message['webpush']['notification']['title']);
        $this->assertSame('user_notification', $message['webpush']['notification']['data']['type']);
        $this->assertSame('bill-123', $message['webpush']['notification']['data']['action_id']);
    }
}
