<?php

namespace App\Events;

use App\Http\Resources\Chat\ChatMessageResource;
use App\Models\ChatMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class ChatMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public readonly ChatMessage $message) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.conversation.' . $this->message->conversation_id)];
    }

    public function broadcastAs(): string
    {
        return 'chat.message.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        // Same shape as the HTTP message payload so clients can de-duplicate by message ID.
        return ['message' => (new ChatMessageResource($this->message))->resolve()];
    }
}
