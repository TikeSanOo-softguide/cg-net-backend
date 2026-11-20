<?php

namespace Tests\Feature;

use App\Enums\ActivityLogEvent;
use App\Enums\PushNotificationDeliveryType;
use App\Enums\PushScheduleStatus;
use App\Models\Admin;
use App\Models\PushSchedule;
use App\Services\Notification\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PushNotificationHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_now_records_an_immediate_notification(): void
    {
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(Permission::findOrCreate('notifications.create', 'web'));
        $titles = [
            'title_en' => 'Immediate campaign',
            'title_zh' => '立即通知',
            'title_my' => 'ချက်ချင်း',
        ];
        $service = \Mockery::mock(PushNotificationService::class);
        $service->shouldReceive('pushNow')->once()->with($titles)->andReturn(1);
        $this->app->instance(PushNotificationService::class, $service);

        $this->actingAs($admin, 'web')
            ->post('/notifications/compose/push-now', $titles)
            ->assertRedirect(route('notifications.compose'))
            ->assertSessionHas('success', 'notification.push.sent');

        $notification = PushSchedule::query()->firstOrFail();
        $this->assertSame(PushNotificationDeliveryType::Immediate, $notification->delivery_type);
        $this->assertSame(PushScheduleStatus::Sent, $notification->status);
        $this->assertNull($notification->scheduled_at);
        $this->assertNotNull($notification->sent_at);
        $this->assertSame($admin->id, $notification->created_by);
    }

    public function test_history_combines_scheduled_and_immediate_pushes_and_applies_filters(): void
    {
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(Permission::findOrCreate('notifications.view', 'web'));

        PushSchedule::query()->create([
            'title_en' => 'Scheduled campaign',
            'title_zh' => '定时通知',
            'title_my' => 'အချိန်ဇယား',
            'scheduled_at' => '2026-04-05 10:00:00',
            'sent_at' => '2026-04-05 10:01:00',
            'status' => PushScheduleStatus::Sent,
        ]);
        PushSchedule::query()->create([
            'title_en' => 'Pending campaign',
            'title_zh' => '待发送通知',
            'title_my' => 'စောင့်ဆိုင်းနေသည်',
            'scheduled_at' => '2026-04-06 10:00:00',
            'status' => PushScheduleStatus::Pending,
        ]);
        PushSchedule::query()->create([
            'title_en' => 'Immediate campaign',
            'title_zh' => '立即通知',
            'title_my' => 'ချက်ချင်း',
            'delivery_type' => PushNotificationDeliveryType::Immediate,
            'scheduled_at' => null,
            'sent_at' => '2026-04-05 10:02:00',
            'status' => PushScheduleStatus::Sent,
        ]);

        $this->actingAs($admin, 'web')
            ->get('/notifications/compose/history?status=sent&delivery_type=scheduled')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Notification/compose/History')
                    ->where('filters.status', 'sent')
                    ->where('filters.delivery_type', 'scheduled')
                    ->has('notifications.data', 1)
                    ->where('notifications.data.0.title_en', 'Scheduled campaign')
                    ->where('notifications.data.0.delivery_type', 'scheduled')
                    ->where('notifications.data.0.status', 'sent')
                    ->has('notifications.data.0.scheduled_at')
                    ->has('notifications.data.0.sent_at')
                    ->has('notifications.data.0.created_at'),
            );
    }

    public function test_schedule_list_shows_only_pending_scheduled_pushes(): void
    {
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(Permission::findOrCreate('notifications.view', 'web'));

        foreach ([
            ['Pending scheduled push', PushNotificationDeliveryType::Scheduled, PushScheduleStatus::Pending],
            ['Failed scheduled push', PushNotificationDeliveryType::Scheduled, PushScheduleStatus::Failed],
            ['Cancelled scheduled push', PushNotificationDeliveryType::Scheduled, PushScheduleStatus::Cancelled],
            ['Sent scheduled push', PushNotificationDeliveryType::Scheduled, PushScheduleStatus::Sent],
            ['Pending immediate push', PushNotificationDeliveryType::Immediate, PushScheduleStatus::Pending],
        ] as [$title, $deliveryType, $status]) {
            PushSchedule::query()->create([
                'title_en' => $title,
                'title_zh' => '标题',
                'title_my' => 'ခေါင်းစဉ်',
                'delivery_type' => $deliveryType,
                'scheduled_at' => $deliveryType === PushNotificationDeliveryType::Scheduled ? now()->addHour() : null,
                'status' => $status,
            ]);
        }

        $this->actingAs($admin, 'web')
            ->get('/notifications/compose')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Notification/compose/Index')
                    ->has('schedules', 1)
                    ->where('schedules.0.title_en', 'Pending scheduled push')
                    ->where('schedules.0.status', 'pending'),
            );
    }

    public function test_history_can_sort_by_scheduled_at_with_null_dates_at_the_bottom(): void
    {
        $admin = Admin::factory()->create();
        $admin->givePermissionTo(Permission::findOrCreate('notifications.view', 'web'));

        PushSchedule::query()->create([
            'title_en' => 'Later scheduled push',
            'title_zh' => '稍后发送',
            'title_my' => 'နောက်မှ',
            'scheduled_at' => '2026-04-06 10:00:00',
            'status' => PushScheduleStatus::Pending,
        ]);
        PushSchedule::query()->create([
            'title_en' => 'Earlier scheduled push',
            'title_zh' => '较早发送',
            'title_my' => 'စောစော',
            'scheduled_at' => '2026-04-05 10:00:00',
            'status' => PushScheduleStatus::Pending,
        ]);
        PushSchedule::query()->create([
            'title_en' => 'Immediate push',
            'title_zh' => '立即发送',
            'title_my' => 'ချက်ချင်း',
            'delivery_type' => PushNotificationDeliveryType::Immediate,
            'scheduled_at' => null,
            'status' => PushScheduleStatus::Sent,
        ]);

        foreach ([
            'asc' => ['Earlier scheduled push', 'Later scheduled push', 'Immediate push'],
            'desc' => ['Later scheduled push', 'Earlier scheduled push', 'Immediate push'],
        ] as $direction => $expectedTitles) {
            $this->actingAs($admin, 'web')
                ->get("/notifications/compose/history?sort=scheduled_at&direction={$direction}")
                ->assertOk()
                ->assertInertia(
                    fn(Assert $page) => $page
                        ->has('notifications.data', 3)
                        ->where('notifications.data.0.title_en', $expectedTitles[0])
                        ->where('notifications.data.1.title_en', $expectedTitles[1])
                        ->where('notifications.data.2.title_en', $expectedTitles[2])
                        ->where('filters.sort', 'scheduled_at')
                        ->where('filters.direction', $direction),
                );
        }

        $this->actingAs($admin, 'web')
            ->get('/notifications/compose/history')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->has('notifications.data', 3)
                    ->where('notifications.data.0.title_en', 'Immediate push')
                    ->where('notifications.data.1.title_en', 'Earlier scheduled push')
                    ->where('notifications.data.2.title_en', 'Later scheduled push')
                    ->where('filters.sort', ''),
            );
    }

    public function test_zero_recipient_immediate_pushes_have_a_distinct_status(): void
    {
        $admin = Admin::factory()->create();
        $admin->givePermissionTo([
            Permission::findOrCreate('notifications.create', 'web'),
            Permission::findOrCreate('notifications.view', 'web'),
        ]);
        $titles = [
            'title_en' => 'No recipients',
            'title_zh' => '没有收件人',
            'title_my' => 'လက်ခံသူမရှိပါ',
        ];
        $service = \Mockery::mock(PushNotificationService::class);
        $service->shouldReceive('pushNow')->once()->with($titles)->andReturn(0);
        $this->app->instance(PushNotificationService::class, $service);

        $this->actingAs($admin, 'web')
            ->post('/notifications/compose/push-now', $titles)
            ->assertRedirect(route('notifications.compose'))
            ->assertSessionHas('error', 'notification.push.no_devices');

        $notification = PushSchedule::query()->firstOrFail();
        $this->assertSame(PushNotificationDeliveryType::Immediate, $notification->delivery_type);
        $this->assertSame(PushScheduleStatus::NoRecipients, $notification->status);
        $this->assertNull($notification->sent_at);
        $this->assertDatabaseHas('activity_log', [
            'event' => ActivityLogEvent::NoRecipients->value,
        ]);

        $this->actingAs($admin, 'web')
            ->get('/notifications/compose/history?status=no_recipients')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->has('notifications.data', 1)
                    ->where('notifications.data.0.title_en', 'No recipients')
                    ->where('notifications.data.0.delivery_type', 'immediate')
                    ->where('notifications.data.0.status', 'no_recipients')
                    ->where('notifications.data.0.scheduled_at', null)
                    ->has('notifications.data.0.sent_at')
                    ->has('notifications.data.0.created_at'),
            );
    }
}
