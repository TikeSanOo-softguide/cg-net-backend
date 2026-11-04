<?php

namespace App\Enums;

enum TopUpCardStatus: string
{
    case Active = 'active';
    case Used = 'used';
    case Expired = 'expired';
    case Blocked = 'blocked';
}
