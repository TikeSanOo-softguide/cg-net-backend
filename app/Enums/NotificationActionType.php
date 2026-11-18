<?php

namespace App\Enums;

enum NotificationActionType: string
{
    case Announcement = 'announcement';
    case Promotion = 'promotion';
    case FtthBill = 'ftth_bill';
    case BillPayment = 'bill_payment';
    case PackageOrder = 'package_order';
}
