<?php

namespace App\Notifications;

use App\Enums\NotificationActionType;
use App\Enums\NotificationCategory;
use App\Enums\NotificationTemplateType;
use App\Enums\PackageOrderNotificationEvent;
use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

class PackageOrderStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly PackageOrderNotificationEvent $event,
        public readonly int $orderId,
        public readonly string $orderNo,
        public readonly string $transactionNo,
        public readonly string $amount,
        public readonly string $productId,
        public readonly string $productName,
        public readonly ?string $refundTransactionNo = null,
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
        $content = $this->content($notifiable);
        $unreadCount = $notifiable->unreadAppNotificationCount();
        $amount = rtrim(rtrim(number_format((float) $this->amount, 2, '.', ''), '0'), '.');
        $data = array_filter(
            [
                'type' => 'package_order',
                'category' => NotificationCategory::Announcement->value,
                'action_type' => NotificationActionType::PackageOrder->value,
                'action_id' => (string) $this->orderId,
                'event' => $this->event->value,
                'order_id' => (string) $this->orderId,
                'order_no' => $this->orderNo,
                'transaction_no' => $this->transactionNo,
                'amount' => $amount,
                'product_id' => $this->productId,
                'product_name' => $this->productName,
                'refund_transaction_no' => $this->refundTransactionNo,
                'unread_count' => (string) $unreadCount,
            ],
            fn ($value) => $value !== null,
        );

        return (new FcmMessage(
            notification: new FcmNotification(title: $content['title'], body: $content['description']),
        ))
            ->data($data)
            ->custom([
                'webpush' => [
                    'headers' => ['Urgency' => 'high'],
                    'notification' => [
                        'title' => $content['title'],
                        'body' => $content['description'],
                        'data' => $data,
                    ],
                ],
            ])
            ->android(['priority' => 'high'])
            ->ios(['payload' => ['aps' => ['sound' => 'default', 'badge' => $unreadCount]]]);
    }

    /**
     * @return array{title: string, description: string}
     */
    public function content(User $notifiable): array
    {
        $templateType = NotificationTemplateType::forPackageOrderEvent($this->event);
        $template = NotificationTemplate::query()->where('type', $templateType->value)->firstOrFail();
        $formattedAmount = rtrim(rtrim(number_format((float) $this->amount, 2), '0'), '.');

        return $template->render($notifiable->lang ?? 'en', [
            'amount' => $formattedAmount.' Points',
            'order_no' => $this->orderNo,
            'product_name' => $this->productName,
            'transaction_no' => $this->transactionNo,
            'refund_transaction_no' => $this->refundTransactionNo ?? '',
        ]);
    }
}
