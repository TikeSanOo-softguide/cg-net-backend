<?php

namespace App\Enums;

enum ChatConversationStatus: string
{
    case Bot = 'bot';
    case Agent = 'agent';
    case Closed = 'closed';
}
