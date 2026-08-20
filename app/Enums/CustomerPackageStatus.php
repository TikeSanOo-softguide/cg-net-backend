<?php

namespace App\Enums;

enum CustomerPackageStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
}
