<?php

namespace App\Services\Notification;

use App\Enums\PushScheduleStatus;
use App\Models\PushSchedule;
use App\Models\User;
use App\Notifications\AdminPushNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class PushNotificationService
{
    /**
     * @param  array{title_en: string, title_zh: string, title_my: string}  $titles
     */
    public function pushNow(array $titles): int
    {
        return $this->broadcast($titles);
    }

    public function processDue(int $limit = 50): int
    {
        $ids = PushSchedule::query()
            ->where('status', PushScheduleStatus::Pending)
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->pluck('id');

        $processed = 0;

        foreach ($ids as $id) {
            if ($this->dispatchScheduleById((int) $id)) {
                $processed++;
            }
        }

        return $processed;
    }

    public function dispatchScheduleById(int $id): bool
    {
        return DB::transaction(function () use ($id): bool {
            $schedule = PushSchedule::query()
                ->whereKey($id)
                ->where('status', PushScheduleStatus::Pending)
                ->lockForUpdate()
                ->first();

            if ($schedule === null) {
                return false;
            }

            try {
                $this->broadcast([
                    'title_en' => $schedule->title_en,
                    'title_zh' => $schedule->title_zh,
                    'title_my' => $schedule->title_my,
                ]);

                $schedule->update([
                    'status' => PushScheduleStatus::Sent,
                    'sent_at' => now(),
                    'error_message' => null,
                ]);
            } catch (Throwable $throwable) {
                Log::error('Failed to dispatch scheduled push notification.', [
                    'push_schedule_id' => $schedule->id,
                    'exception' => $throwable->getMessage(),
                ]);

                $schedule->update([
                    'status' => PushScheduleStatus::Failed,
                    'error_message' => $throwable->getMessage(),
                ]);
            }

            return true;
        });
    }

    /**
     * @param  array{title_en: string, title_zh: string, title_my: string}  $titles
     */
    protected function broadcast(array $titles): int
    {
        $notification = new AdminPushNotification(
            titleEn: $titles['title_en'],
            titleZh: $titles['title_zh'],
            titleMy: $titles['title_my'],
        );

        $recipients = 0;

        User::query()
            ->whereHas('deviceTokens')
            ->orderBy('id')
            ->chunkById(200, function ($users) use ($notification, &$recipients): void {
                Notification::send($users, $notification);
                $recipients += $users->count();
            });

        return $recipients;
    }
}
