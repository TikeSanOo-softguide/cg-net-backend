<?php

namespace App\Enums;

enum BillPaymentNotificationEvent: string
{
    case Processing = 'processing';
    case Completed = 'completed';
    case Refunded = 'refunded';
}
