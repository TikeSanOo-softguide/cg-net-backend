<?php

namespace App\Enums;

enum ChatConversationStatus: string
{
    case Open = 'open';
    case Bot = 'bot';
    case WaitingAgent = 'waiting_agent';
    case WithAgent = 'with_agent';
    case Closed = 'closed';

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return array_values(array_map(
            fn (self $status) => $status->value,
            array_filter(self::cases(), fn (self $status) => $status->isActive()),
        ));
    }

    public function isActive(): bool
    {
        return $this !== self::Closed;
    }

    public function isChatFlow(): bool
    {
        return $this === self::Bot;
    }

    public function isWaiting(): bool
    {
        return $this === self::WaitingAgent;
    }

    // `with_agent` is a legacy alias for a live conversation; new assignments use `open`.
    public function isLive(): bool
    {
        return $this === self::Open || $this === self::WithAgent;
    }
}
