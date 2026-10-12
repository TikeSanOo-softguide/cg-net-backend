<?php

namespace App\Services\Notification;

use App\Enums\AnnouncementType;
use App\Enums\NotificationActionType;
use App\Enums\NotificationCategory;
use App\Enums\PushScheduleStatus;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\Notification as UserNotification;
use App\Models\Promotion;
use App\Models\PushSchedule;
use App\Models\User;
use App\Notifications\AdminPushNotification;
use App\Notifications\CampaignNotification;
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

    public function processDueTitles(int $limit = 50): int
    {
        return $this->dispatchDueAnnouncements($limit) + $this->dispatchDuePromotions($limit);
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

    private function dispatchDueAnnouncements(int $limit): int
    {
        $ids = Announcement::query()
            ->where('is_active', true)
            ->whereNull('push_sent_at')
            ->where(function ($query): void {
                $query->whereNull('start_date')->orWhere('start_date', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $processed = 0;

        foreach ($ids as $id) {
            if ($this->dispatchAnnouncementById((int) $id)) {
                $processed++;
            }
        }

        return $processed;
    }

    private function dispatchDuePromotions(int $limit): int
    {
        $today = now()->toDateString();

        $ids = Promotion::query()
            ->where('is_active', true)
            ->whereNull('push_sent_at')
            ->where(function ($query) use ($today): void {
                $query->whereNull('start_date')->orWhereDate('start_date', '<=', $today);
            })
            ->where(function ($query) use ($today): void {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $processed = 0;

        foreach ($ids as $id) {
            if ($this->dispatchPromotionById((int) $id)) {
                $processed++;
            }
        }

        return $processed;
    }

    private function dispatchAnnouncementById(int $id): bool
    {
        return DB::transaction(function () use ($id): bool {
            $announcement = Announcement::query()
                ->whereKey($id)
                ->where('is_active', true)
                ->whereNull('push_sent_at')
                ->where(function ($query): void {
                    $query->whereNull('start_date')->orWhere('start_date', '<=', now());
                })
                ->where(function ($query): void {
                    $query->whereNull('end_date')->orWhere('end_date', '>=', now());
                })
                ->lockForUpdate()
                ->first();

            if ($announcement === null) {
                return false;
            }

            return $this->sendTitlesOnce($announcement);
        });
    }

    private function dispatchPromotionById(int $id): bool
    {
        $today = now()->toDateString();

        return DB::transaction(function () use ($id, $today): bool {
            $promotion = Promotion::query()
                ->whereKey($id)
                ->where('is_active', true)
                ->whereNull('push_sent_at')
                ->where(function ($query) use ($today): void {
                    $query->whereNull('start_date')->orWhereDate('start_date', '<=', $today);
                })
                ->where(function ($query) use ($today): void {
                    $query->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
                })
                ->lockForUpdate()
                ->first();

            if ($promotion === null) {
                return false;
            }

            return $this->sendTitlesOnce($promotion);
        });
    }

    private function sendTitlesOnce(Announcement|Promotion $record): bool
    {
        $category = match (true) {
            $record instanceof Promotion => NotificationCategory::Promotion,
            $record->type === AnnouncementType::System => NotificationCategory::System,
            default => NotificationCategory::Announcement,
        };
        $actionType =
            $record instanceof Promotion ? NotificationActionType::Promotion : NotificationActionType::Announcement;
        $content = [
            'title_en' => $record->title_en,
            'title_zh' => $record->title_zh,
            'title_my' => $record->title_my,
            'body_en' => $record instanceof Promotion ? $record->description_en : $record->content_en,
            'body_zh' => $record instanceof Promotion ? $record->description_zh : $record->content_zh,
            'body_my' => $record instanceof Promotion ? $record->description_my : $record->content_my,
        ];
        if ($record instanceof Promotion) {
            $content['slug'] = $record->slug;
        }

        $notificationCount = 0;
        $pushCount = 0;

        try {
            User::query()
                ->where('status', UserStatus::Active)
                ->with('deviceTokens')
                ->orderBy('id')
                ->chunkById(200, function ($users) use (
                    $record,
                    $category,
                    $actionType,
                    $content,
                    &$notificationCount,
                    &$pushCount,
                ): void {
                    foreach ($users as $user) {
                        $notification = UserNotification::query()->firstOrCreate(
                            [
                                'user_id' => $user->id,
                                'action_type' => $actionType->value,
                                'action_id' => (string) $record->id,
                            ],
                            [
                                'category' => $category,
                                'templateable_type' => $record::class,
                                'templateable_id' => $record->id,
                                'template_data' => $content,
                            ],
                        );
                        $notificationCount++;

                        if ($notification->sent_at !== null || $user->deviceTokens->isEmpty()) {
                            continue;
                        }

                        $locale = in_array($user->lang, ['en', 'my', 'zh'], true) ? $user->lang : 'en';
                        Notification::send(
                            $user,
                            new CampaignNotification(
                                title: $content["title_{$locale}"],
                                body: $content["body_{$locale}"],
                                category: $category->value,
                                actionType: $actionType,
                                actionId: (string) $record->id,
                                slug: $content['slug'] ?? null,
                            ),
                        );
                        $notification->update(['sent_at' => now()]);
                        $pushCount++;
                    }
                });
        } catch (Throwable $throwable) {
            Log::error('Failed to send campaign notifications.', [
                'source' => $record::class,
                'id' => $record->id,
                'exception' => $throwable->getMessage(),
            ]);

            return false;
        }

        if ($notificationCount === 0 || $pushCount === 0) {
            return false;
        }

        $record->update(['push_sent_at' => now()]);

        return true;
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
