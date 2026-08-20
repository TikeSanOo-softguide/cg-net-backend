<?php

namespace App\Enums;

enum ConnectionStatus: string
{
    case Good = 'good';
    case Slow = 'slow';
    case Offline = 'offline';
}
