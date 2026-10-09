<?php

namespace App\Events;

use App\Enums\ChatConversationStatus;
use App\Models\ChatConversation;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class ChatConversationStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly ChatConversation $conversation,
        public readonly ?ChatConversationStatus $previousStatus,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.conversation.' . $this->conversation->id),
            new PrivateChannel('support.conversations'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat.conversation.status_changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $status = $this->conversation->status;

        return [
            'conversation_id' => $this->conversation->id,
            'status' => $status->value,
            'previous_status' => $this->previousStatus?->value,
            'agent' => $this->conversation->agent
                ? ['id' => $this->conversation->agent->id, 'username' => $this->conversation->agent->username]
                : null,
            'can_send_message' => $status->isLive(),
            'can_select_option' => $status->isChatFlow(),
            'is_closed' => $status === ChatConversationStatus::Closed,
            'can_start_new_conversation' => $status === ChatConversationStatus::Closed,
        ];
    }
}
