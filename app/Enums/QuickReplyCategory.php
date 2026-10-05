<?php

namespace App\Enums;

enum QuickReplyCategory: string
{
    case Internet = 'internet';
    case Payment = 'payment';
    case Package = 'package';
    case Technical = 'technical';
    case Account = 'account';
}
