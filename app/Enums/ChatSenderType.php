<?php

namespace App\Enums;

enum ChatSenderType: string
{
    case User = 'user';
    case Bot = 'bot';
    case Agent = 'agent';
}
