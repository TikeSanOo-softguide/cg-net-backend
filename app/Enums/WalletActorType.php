<?php

namespace App\Enums;

enum WalletActorType: string
{
    case User = 'user';
    case Admin = 'admin';
    case System = 'system';
}
