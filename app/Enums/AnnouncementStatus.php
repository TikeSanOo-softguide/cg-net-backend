<?php

namespace App\Enums;

enum AnnouncementStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Pending = 'pending';
    case Expired = 'expired';
}
