<?php

namespace App\Enums;

enum BatchStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Expired = 'expired';
    case Blocked = 'blocked';
}
