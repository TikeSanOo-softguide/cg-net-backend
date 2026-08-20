<?php

namespace Database\Factories;

use App\Enums\ChatConversationStatus;
use App\Models\Admin;
use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatConversation>
 */
class ChatConversationFactory extends Factory
{
    public function definition(): array
    {
        $status = fake()->randomElement(ChatConversationStatus::cases());

        return [
            'user_id' => User::factory(),
            'agent_id' => $status === ChatConversationStatus::Bot ? null : Admin::factory(),
            'status' => $status,
        ];
    }
}
