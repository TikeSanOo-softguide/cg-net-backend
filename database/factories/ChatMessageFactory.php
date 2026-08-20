<?php

namespace Database\Factories;

use App\Enums\ChatSenderType;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'conversation_id' => ChatConversation::factory(),
            'sender_type' => fake()->randomElement(ChatSenderType::cases()),
            'message' => fake()->sentence(),
            'attachment_path' => fake()->optional(0.1)->passthrough('chat/'.fake()->uuid().'.jpg'),
        ];
    }
}
