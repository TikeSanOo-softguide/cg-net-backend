<?php

namespace App\Enums;

enum NotificationActionType: string
{
    case Announcement = 'announcement';
    case Promotion = 'promotion';
    case FtthBill = 'ftth_bill';
    case FtthBillPayment = 'ftth_bill_payment';
}
