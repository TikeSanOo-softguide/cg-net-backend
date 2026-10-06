<?php

namespace App\Enums;

enum QuickReplyCategory: string
{
    case Welcome = 'welcome';
    case Internet = 'internet';
    case Payment = 'payment';
    case Package = 'package';
    case Technical = 'technical';
    case Account = 'account';
    case Closing = 'closing';
}
