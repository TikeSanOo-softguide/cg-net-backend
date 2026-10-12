<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

class AdminPushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $titleEn,
        public readonly string $titleZh,
        public readonly string $titleMy,
    ) {
        $this->afterCommit();
        $this->onQueue('notifications');
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return $notifiable->deviceTokens()->exists() ? [FcmChannel::class] : [];
    }

    public function toFcm(User $notifiable): FcmMessage
    {
        return (new FcmMessage(notification: new FcmNotification(
            title: $this->titleEn,
            body: $this->titleEn,
        )))
            ->data([
                'type' => 'admin_push',
                'title_en' => $this->titleEn,
                'title_zh' => $this->titleZh,
                'title_my' => $this->titleMy,
            ])
            ->custom([
                'webpush' => [
                    'headers' => ['Urgency' => 'high'],
                    'notification' => [
                        'title' => $this->titleEn,
                        'body' => $this->titleEn,
                        'data' => [
                            'type' => 'admin_push',
                            'title_en' => $this->titleEn,
                            'title_zh' => $this->titleZh,
                            'title_my' => $this->titleMy,
                        ],
                    ],
                ],
            ])
            ->android(['priority' => 'high'])
            ->ios(['payload' => ['aps' => ['sound' => 'default']]]);
    }
}
