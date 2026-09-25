<?php

namespace App\Enums;

enum TopUpCardStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Used = 'used';
    case Expired = 'expired';
    case Blocked = 'blocked';
}
