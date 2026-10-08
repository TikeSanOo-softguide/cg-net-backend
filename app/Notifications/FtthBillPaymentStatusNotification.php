<?php

namespace App\Notifications;

use App\Enums\BillPaymentNotificationEvent;
use App\Enums\NotificationTemplateType;
use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

class FtthBillPaymentStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly BillPaymentNotificationEvent $event,
        public readonly string $transactionNo,
        public readonly int $amount,
        public readonly string $accountNumber,
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

        return (new FcmMessage(notification: new FcmNotification(title: $content['title'], body: $content['description'])))
            ->data(
                array_filter(
                    [
                        'type' => 'ftth_bill_payment',
                        'event' => $this->event->value,
                        'transaction_no' => $this->transactionNo,
                        'amount' => (string) $this->amount,
                        'broadband_account_number' => $this->accountNumber,
                        'refund_transaction_no' => $this->refundTransactionNo,
                    ],
                    fn($value) => $value !== null,
                ),
            )
            ->custom([
                'webpush' => [
                    'headers' => ['Urgency' => 'high'],
                    'notification' => [
                        'title' => $content['title'],
                        'body' => $content['description'],
                        'data' => array_filter(
                            [
                                'type' => 'ftth_bill_payment',
                                'event' => $this->event->value,
                                'transaction_no' => $this->transactionNo,
                                'amount' => (string) $this->amount,
                                'broadband_account_number' => $this->accountNumber,
                                'refund_transaction_no' => $this->refundTransactionNo,
                            ],
                            fn($value) => $value !== null,
                        ),
                    ],
                ],
            ])
            ->android(['priority' => 'high'])
            ->ios(['payload' => ['aps' => ['sound' => 'default']]]);
    }

    /**
     * @return array{title: string, description: string}
     */
    public function content(User $notifiable): array
    {
        $templateType = NotificationTemplateType::forBillPaymentEvent($this->event);
        $template = NotificationTemplate::query()
            ->where('type', $templateType->value)
            ->firstOrFail();

        return $template->render($notifiable->lang, [
            'amount' => number_format($this->amount).' Points',
            'account_number' => $this->accountNumber,
            'transaction_no' => $this->transactionNo,
            'refund_transaction_no' => $this->refundTransactionNo ?? '',
        ]);
    }

}
