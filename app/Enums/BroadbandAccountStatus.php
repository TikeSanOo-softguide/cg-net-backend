<?php

namespace App\Enums;

enum BroadbandAccountStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Terminated = 'terminated';
}
