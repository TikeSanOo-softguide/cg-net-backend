<?php

namespace Tests\Feature;

use App\Enums\BillPaymentNotificationEvent;
use App\Listeners\PruneInvalidFcmTokens;
use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\FtthBillPaymentStatusNotification;
use App\Notifications\FtthBillDueNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationFailed;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\SendReport;
use NotificationChannels\Fcm\FcmChannel;
use Tests\TestCase;

class DeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_device_token(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/device-tokens', ['token' => 'fcm-token-1', 'platform' => 'android'])
            ->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-token-1',
            'platform' => 'android',
        ]);
        $this->assertSame(['fcm-token-1'], $user->routeNotificationForFcm());
    }

    public function test_web_fcm_token_is_registered_and_receives_due_bill_notification_payload(): void
    {
        $user = User::factory()->create();

        $this->withToken($user->createToken('web-test')->plainTextToken, 'Bearer')
            ->postJson('/api/device-tokens', ['token' => 'web-fcm-token', 'platform' => 'web'])
            ->assertOk();

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'web-fcm-token',
            'platform' => 'web',
        ]);

        $notification = new FtthBillDueNotification(
            title: 'မကြာမီ FTTH ဘေလ်ကျသင့်မည်',
            body: 'အသေးစိတ်ကြည့်ရန် နှိပ်ပါ။',
            actionId: 'bill-123',
        );

        $this->assertSame([FcmChannel::class], $notification->via($user));
        $this->assertSame(['web-fcm-token'], $user->routeNotificationForFcm());
        $message = $notification->toFcm($user)->toArray();
        $this->assertSame('bill-123', $message['data']['action_id']);
        $this->assertSame('မကြာမီ FTTH ဘေလ်ကျသင့်မည်', $message['webpush']['notification']['title']);
    }

    public function test_registering_existing_token_moves_it_to_current_user(): void
    {
        $previousOwner = User::factory()->create();
        $user = User::factory()->create();
        DeviceToken::query()->create(['user_id' => $previousOwner->id, 'token' => 'shared-device']);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/device-tokens', ['token' => 'shared-device'])
            ->assertOk();

        $this->assertSame(1, DeviceToken::query()->where('token', 'shared-device')->count());
        $this->assertDatabaseHas('device_tokens', ['user_id' => $user->id, 'token' => 'shared-device']);
    }

    public function test_user_can_remove_only_their_own_device_token(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        DeviceToken::query()->create(['user_id' => $user->id, 'token' => 'mine']);
        DeviceToken::query()->create(['user_id' => $other->id, 'token' => 'theirs']);

        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token, 'Bearer')
            ->deleteJson('/api/device-tokens', ['token' => 'mine'])
            ->assertOk();
        $this->withToken($token, 'Bearer')
            ->deleteJson('/api/device-tokens', ['token' => 'theirs'])
            ->assertOk();

        $this->assertDatabaseMissing('device_tokens', ['token' => 'mine']);
        $this->assertDatabaseHas('device_tokens', ['token' => 'theirs']);
    }

    public function test_logout_removes_device_tokens(): void
    {
        $user = User::factory()->create();
        DeviceToken::query()->create(['user_id' => $user->id, 'token' => 'logout-device']);

        $this->withToken($user->createToken('test')->plainTextToken, 'Bearer')
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertDatabaseMissing('device_tokens', ['token' => 'logout-device']);
    }

    public function test_bill_payment_notification_skips_users_without_device_tokens(): void
    {
        $user = User::factory()->create();
        $notification = $this->makeNotification(BillPaymentNotificationEvent::Completed);

        $this->assertSame([], $notification->via($user));

        DeviceToken::query()->create(['user_id' => $user->id, 'token' => 'device-a']);

        $this->assertSame([FcmChannel::class], $notification->via($user));
    }

    public function test_bill_payment_notification_builds_fcm_payload(): void
    {
        $user = User::factory()->create();
        $notification = $this->makeNotification(BillPaymentNotificationEvent::Refunded, 'REFUND-1');

        $message = $notification->toFcm($user)->toArray();

        $this->assertSame('Bill payment failed', $message['notification']['title']);
        $this->assertStringContainsString('1,500 MMK', $message['notification']['body']);
        $this->assertStringContainsString('refunded', $message['notification']['body']);
        $this->assertSame(
            [
                'type' => 'ftth_bill_payment',
                'category' => 'announcement',
                'action_type' => 'ftth_bill_payment',
                'action_id' => 'FTTH-1',
                'event' => 'refunded',
                'transaction_no' => 'FTTH-1',
                'amount' => '1500',
                'broadband_account_number' => 'CG12345678',
                'refund_transaction_no' => 'REFUND-1',
            ],
            $message['data'],
        );
        $this->assertSame($message['notification']['title'], $message['webpush']['notification']['title']);
        $this->assertSame($message['data'], $message['webpush']['notification']['data']);
    }

    public function test_unknown_fcm_tokens_are_pruned(): void
    {
        $user = User::factory()->create();
        DeviceToken::query()->create(['user_id' => $user->id, 'token' => 'dead-token']);
        DeviceToken::query()->create(['user_id' => $user->id, 'token' => 'live-token']);

        $report = SendReport::failure(
            MessageTarget::with(MessageTarget::TOKEN, 'dead-token'),
            new NotFound('Requested entity was not found.'),
        );

        (new PruneInvalidFcmTokens())->handle(
            new NotificationFailed(
                $user,
                $this->makeNotification(BillPaymentNotificationEvent::Completed),
                FcmChannel::class,
                ['report' => $report],
            ),
        );

        $this->assertDatabaseMissing('device_tokens', ['token' => 'dead-token']);
        $this->assertDatabaseHas('device_tokens', ['token' => 'live-token']);
    }

    private function makeNotification(
        BillPaymentNotificationEvent $event,
        ?string $refundTransactionNo = null,
    ): FtthBillPaymentStatusNotification {
        return new FtthBillPaymentStatusNotification(
            event: $event,
            transactionNo: 'FTTH-1',
            amount: 1500,
            accountNumber: 'CG12345678',
            refundTransactionNo: $refundTransactionNo,
        );
    }
}
