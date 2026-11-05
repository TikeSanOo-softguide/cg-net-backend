<?php

namespace App\Events;

use App\Models\ChatConversation;
use Illuminate\Foundation\Events\Dispatchable;

class LiveChatRequested
{
    use Dispatchable;

    public function __construct(public readonly ChatConversation $conversation) {}
}
