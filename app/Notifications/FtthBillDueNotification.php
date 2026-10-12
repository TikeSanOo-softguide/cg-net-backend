<?php

namespace App\Notifications;

use App\Enums\NotificationActionType;
use App\Models\User;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

class FtthBillDueNotification extends Notification
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly string $actionId,
    ) {}

    /**
     * @return list<class-string>
     */
    public function via(User $notifiable): array
    {
        return $notifiable->deviceTokens()->exists() ? [FcmChannel::class] : [];
    }

    public function toFcm(User $notifiable): FcmMessage
    {
        $data = [
            'type' => 'user_notification',
            'action_type' => NotificationActionType::FtthBill->value,
            'action_id' => $this->actionId,
        ];

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
