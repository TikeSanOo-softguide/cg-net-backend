<?php

namespace App\Enums;

enum NotificationCategory: string
{
    case ServiceUpdate = 'service_update';
    case Account = 'account';
    case Promotion = 'promotion';
}
