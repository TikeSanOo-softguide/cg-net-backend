<?php

namespace App\Enums;

enum ChatConversationStatus: string
{
    case Open = 'open';
    case Bot = 'bot';
    case WaitingAgent = 'waiting_agent';
    case WithAgent = 'with_agent';
    case Closed = 'closed';
}
