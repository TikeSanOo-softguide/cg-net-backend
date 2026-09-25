<?php

namespace App\Models;

use App\Enums\ChatSenderType;
use App\Enums\ChatMessageType;
use Database\Factories\ChatMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'conversation_id',
    'sender_type',
    'message_type',
    'message',
    'attachment_path',
    'option_id',
    'is_read',
])]
class ChatMessage extends Model
{
    /** @use HasFactory<ChatMessageFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'sender_type' => ChatSenderType::class,
            'message_type' => ChatMessageType::class,
            'is_read' => 'boolean',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(ChatFlowOption::class, 'option_id');
    }
}
