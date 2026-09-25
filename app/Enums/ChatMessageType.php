<?php

namespace App\Enums;

enum ChatMessageType: string
{
    case Text = 'text';
    case Option = 'option';
    case System = 'system';
}
