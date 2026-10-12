<?php

namespace App\Enums;

enum NotificationCategory: string
{
    case ServiceUpdate = 'service_update';
    case Account = 'account';
    case Announcement = 'announcement';
    case System = 'system';
    case Promotion = 'promotion';
    case BillAlert = 'bill_alert';
}
