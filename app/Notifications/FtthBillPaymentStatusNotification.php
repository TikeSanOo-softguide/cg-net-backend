<?php

namespace App\Notifications;

use App\Enums\BillPaymentNotificationEvent;
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
        return (new FcmMessage(notification: new FcmNotification(title: $this->title(), body: $this->body())))
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
            ->android(['priority' => 'high'])
            ->ios(['payload' => ['aps' => ['sound' => 'default']]]);
    }

    public function title(): string
    {
        return match ($this->event) {
            BillPaymentNotificationEvent::Processing => 'Bill payment processing',
            BillPaymentNotificationEvent::Completed => 'Bill payment successful',
            BillPaymentNotificationEvent::Refunded => 'Bill payment failed',
        };
    }

    public function body(): string
    {
        $amount = number_format($this->amount) . ' Points';

        return match ($this->event) {
            BillPaymentNotificationEvent::Processing
                => "Your {$amount} payment for account {$this->accountNumber} is being processed.",
            BillPaymentNotificationEvent::Completed
                => "Your {$amount} payment for account {$this->accountNumber} was successful.",
            BillPaymentNotificationEvent::Refunded
                => "Your {$amount} payment for account {$this->accountNumber} could not be completed. The amount has been refunded to your wallet.",
        };
    }
}
