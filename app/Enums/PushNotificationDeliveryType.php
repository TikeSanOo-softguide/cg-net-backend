<?php

namespace App\Enums;

enum PushNotificationDeliveryType: string
{
    case Immediate = 'immediate';
    case Scheduled = 'scheduled';
}
