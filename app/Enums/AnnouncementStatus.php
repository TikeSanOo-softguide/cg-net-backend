<?php

namespace App\Enums;

enum AnnouncementStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Expired = 'expired';
}
