<?php

namespace App\Enums;

enum WalletStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Suspended = 'suspended';
    case Frozen = 'frozen';
}
