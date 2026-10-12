<?php

namespace App\Notifications;

use App\Enums\NotificationActionType;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

class CampaignNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly string $category,
        public readonly NotificationActionType $actionType,
        public readonly string $actionId,
        public readonly ?string $slug = null,
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
        $data = array_filter(
            [
                'type' => 'user_notification',
                'category' => $this->category,
                'action_type' => $this->actionType->value,
                'action_id' => $this->actionId,
                'slug' => $this->slug,
            ],
            fn(?string $value): bool => $value !== null,
        );

        return (new FcmMessage(notification: new FcmNotification(title: $this->title, body: $this->body)))
            ->data($data)
            ->custom([
                'webpush' => [
                    'headers' => ['Urgency' => 'high'],
                    'notification' => [
                        'title' => $this->title,
                        'body' => $this->body,
                        'data' => $data,
                    ],
                ],
            ])
            ->android(['priority' => 'high'])
            ->ios(['payload' => ['aps' => ['sound' => 'default']]]);
    }
}
