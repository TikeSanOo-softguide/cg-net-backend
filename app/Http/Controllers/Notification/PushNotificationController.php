<?php

namespace App\Http\Controllers\Notification;

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

class PushNotificationController extends Controller
{
    public function index(): Response
    {
        $schedules = PushSchedule::query()
            ->whereIn('status', [
                PushScheduleStatus::Pending,
                PushScheduleStatus::Failed,
                PushScheduleStatus::Cancelled,
            ])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 WHEN status = 'failed' THEN 1 ELSE 2 END")
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (PushSchedule $schedule) => $this->payload($schedule));

        return Inertia::render('Notification/compose/Index', [
            'schedules' => $schedules,
        ]);
    }

    public function pushNow(StorePushNotificationRequest $request, PushNotificationService $service): RedirectResponse
    {
        $data = $request->validated();

        $recipients = $service->pushNow($data);

        activity('notifications')
            ->causedBy($request->user())
            ->event('pushed')
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
            'scheduled_at' => $data['scheduled_at'],
            'status' => PushScheduleStatus::Pending,
            'created_by' => $request->user()?->id,
        ]);

        activity('notifications')
            ->causedBy($request->user())
            ->performedOn($schedule)
            ->event('scheduled')
            ->log('push_scheduled');

        return redirect()
            ->route('notifications.compose')
            ->with('success', 'notification.push.scheduled');
    }

    public function cancel(Request $request, PushSchedule $schedule): RedirectResponse
    {
        if ($schedule->status !== PushScheduleStatus::Pending) {
            return redirect()
                ->route('notifications.compose')
                ->with('error', 'notification.push.cancel_failed');
        }

        $schedule->update([
            'status' => PushScheduleStatus::Cancelled,
        ]);

        activity('notifications')
            ->causedBy($request->user())
            ->performedOn($schedule)
            ->event('cancelled')
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
