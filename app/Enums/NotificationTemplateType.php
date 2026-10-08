<?php

namespace App\Enums;

enum NotificationTemplateType: string
{
    case BillAlert = 'bill_alert';
    case FtthBillPaymentProcessing = 'ftth_bill_payment_processing';
    case FtthBillPaymentCompleted = 'ftth_bill_payment_completed';
    case FtthBillPaymentRefunded = 'ftth_bill_payment_refunded';

    public static function forBillPaymentEvent(BillPaymentNotificationEvent $event): self
    {
        return match ($event) {
            BillPaymentNotificationEvent::Processing => self::FtthBillPaymentProcessing,
            BillPaymentNotificationEvent::Completed => self::FtthBillPaymentCompleted,
            BillPaymentNotificationEvent::Refunded => self::FtthBillPaymentRefunded,
        };
    }
}
