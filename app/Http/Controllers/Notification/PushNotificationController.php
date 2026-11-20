<?php

namespace App\Http\Controllers\Notification;

use App\Enums\ActivityLogEvent;
use App\Enums\ActivityLogName;
use App\Enums\PushNotificationDeliveryType;
use App\Enums\PushScheduleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\StorePushNotificationRequest;
use App\Http\Requests\Notification\StorePushScheduleRequest;
use App\Models\PushSchedule;
use App\Services\Notification\PushNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PushNotificationController extends Controller
{
    public function index(): Response
    {
        $schedules = PushSchedule::query()
            ->where('delivery_type', PushNotificationDeliveryType::Scheduled)
            ->where('status', PushScheduleStatus::Pending)
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (PushSchedule $schedule) => $this->payload($schedule));

        return Inertia::render('Notification/compose/Index', [
            'schedules' => $schedules,
        ]);
    }

    public function history(Request $request): Response
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,sent,failed,no_recipients,cancelled'],
            'delivery_type' => ['nullable', 'in:scheduled,immediate'],
            'sort' => ['nullable', 'in:scheduled_at'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);
        $filters = [
            'status' => $validated['status'] ?? '',
            'delivery_type' => $validated['delivery_type'] ?? '',
            'sort' => $validated['sort'] ?? '',
            'direction' => $validated['direction'] ?? 'desc',
        ];
        $notifications = PushSchedule::query()
            ->when(
                $filters['delivery_type'] !== '',
                fn($query) => $query->where('delivery_type', $filters['delivery_type']),
            )
            ->when($filters['status'] !== '', fn($query) => $query->where('status', $filters['status']))
            ->when(
                $filters['sort'] === 'scheduled_at',
                function ($query) use ($filters): void {
                    $query
                        ->orderByRaw('CASE WHEN scheduled_at IS NULL THEN 1 ELSE 0 END')
                        ->orderBy('scheduled_at', $filters['direction']);
                },
                fn($query) => $query->orderByDesc('created_at'),
            )
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn(PushSchedule $notification) => [
                'id' => (string) $notification->id,
                'title_en' => $notification->title_en,
                'title_zh' => $notification->title_zh,
                'title_my' => $notification->title_my,
                'status' => $notification->status->value,
                'delivery_type' => $notification->delivery_type->value,
                'scheduled_at' => $notification->scheduled_at?->toIso8601String(),
                'sent_at' => $notification->sent_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
                'updated_at' => $notification->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('Notification/compose/History', [
            'notifications' => $notifications,
            'filters' => $filters,
        ]);
    }

    public function pushNow(StorePushNotificationRequest $request, PushNotificationService $service): RedirectResponse
    {
        $data = $request->validated();
        $notification = PushSchedule::query()->create([
            ...$data,
            'delivery_type' => PushNotificationDeliveryType::Immediate,
            'scheduled_at' => null,
            'status' => PushScheduleStatus::Pending,
            'created_by' => $request->user()?->id,
        ]);

        try {
            $recipients = $service->pushNow($data);
        } catch (Throwable $exception) {
            $notification->update([
                'status' => PushScheduleStatus::Failed,
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $status = $recipients === 0 ? PushScheduleStatus::NoRecipients : PushScheduleStatus::Sent;
        $notification->update([
            'status' => $status,
            'sent_at' => $recipients > 0 ? now() : null,
        ]);

        activity(ActivityLogName::PushNotification->value)
            ->causedBy($request->user())
            ->performedOn($notification)
            ->event($recipients === 0 ? ActivityLogEvent::NoRecipients->value : ActivityLogEvent::Pushed->value)
            ->withProperties([...$data, 'recipients' => $recipients])
            ->log('push_sent_now');

        if ($recipients === 0) {
            return redirect()
                ->route('notifications.compose')
                ->with('error', 'notification.push.no_devices');
        }

        return redirect()
            ->route('notifications.compose')
            ->with('success', 'notification.push.sent');
    }

    public function schedule(StorePushScheduleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $schedule = PushSchedule::query()->create([
            'title_en' => $data['title_en'],
            'title_zh' => $data['title_zh'],
            'title_my' => $data['title_my'],
            'delivery_type' => PushNotificationDeliveryType::Scheduled,
            'scheduled_at' => $data['scheduled_at'],
            'status' => PushScheduleStatus::Pending,
            'created_by' => $request->user()?->id,
        ]);

        activity(ActivityLogName::PushNotification->value)
            ->causedBy($request->user())
            ->performedOn($schedule)
            ->event(ActivityLogEvent::Scheduled->value)
            ->log('push_scheduled');

        return redirect()
            ->route('notifications.compose')
            ->with('success', 'notification.push.scheduled');
    }

    public function cancel(Request $request, PushSchedule $schedule): RedirectResponse
    {
        if (
            $schedule->delivery_type !== PushNotificationDeliveryType::Scheduled ||
            $schedule->status !== PushScheduleStatus::Pending
        ) {
            return redirect()
                ->route('notifications.compose')
                ->with('error', 'notification.push.cancel_failed');
        }

        $schedule->update([
            'status' => PushScheduleStatus::Cancelled,
        ]);

        activity(ActivityLogName::PushNotification->value)
            ->causedBy($request->user())
            ->performedOn($schedule)
            ->event(ActivityLogEvent::Cancelled->value)
            ->log('push_cancelled');

        return redirect()
            ->route('notifications.compose')
            ->with('success', 'notification.push.cancelled');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(PushSchedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'title_en' => $schedule->title_en,
            'title_zh' => $schedule->title_zh,
            'title_my' => $schedule->title_my,
            'scheduled_at' => $schedule->scheduled_at?->toIso8601String(),
            'status' => $schedule->status->value,
            'error_message' => $schedule->error_message,
            'created_at' => $schedule->created_at?->toIso8601String(),
        ];
    }
}
