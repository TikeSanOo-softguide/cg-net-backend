<?php

namespace App\Enums;

enum PackageOrderNotificationEvent: string
{
    case Processing = 'processing';
    case Completed = 'completed';
    case Refunded = 'refunded';
}
