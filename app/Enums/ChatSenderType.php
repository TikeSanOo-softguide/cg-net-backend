<?php

namespace App\Enums;

enum ChatSenderType: string
{
    case Customer = 'customer';
    case Agent = 'agent';
    case System = 'system';
}
