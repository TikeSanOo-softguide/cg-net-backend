<?php

namespace Tests\Feature;

use App\Enums\ChatConversationStatus;
use App\Enums\ChatFlowOptionAction;
use App\Models\Admin;
use App\Models\ChatConversation;
use App\Models\ChatFlowOption;
use App\Models\ChatFlowStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChatConversationSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_conversation_page_loads_current_step_options(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();
        $step = ChatFlowStep::query()->create([
            'name' => 'Main Menu',
            'message_en' => 'How can we help?',
            'message_my' => 'မည်သို့ကူညီရမလဲ။',
            'message_zh' => '我们能如何帮助您？',
            'is_start' => true,
        ]);
        ChatFlowOption::query()->create([
            'step_id' => $step->id,
            'option_en' => 'Internet',
            'option_my' => 'အင်တာနက်',
            'option_zh' => '互联网',
            'action' => ChatFlowOptionAction::GoToStep->value,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $conversation = ChatConversation::query()->create([
            'user_id' => $user->id,
            'current_step_id' => $step->id,
            'status' => ChatConversationStatus::Bot,
        ]);

        $this->actingAs($admin, 'web')
            ->get('/support/conversations?conversation='.$conversation->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Support/chatConversations/Index')
                ->where('selectedConversation.current_step.options.0.option_en', 'Internet'));
    }

    public function test_support_groups_a_customers_conversation_records_under_the_latest_status(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();
        $closedConversation = ChatConversation::query()->create([
            'user_id' => $user->id,
            'status' => ChatConversationStatus::Closed,
        ]);
        $closedMessage = $closedConversation->messages()->create([
            'sender_type' => 'customer',
            'message' => 'Message from the closed conversation',
            'is_read' => false,
        ]);
        $currentConversation = ChatConversation::query()->create([
            'user_id' => $user->id,
            'status' => ChatConversationStatus::Open,
            'agent_id' => $admin->id,
        ]);
        $currentConversation->messages()->create([
            'sender_type' => 'agent',
            'message' => 'Current conversation reply',
            'is_read' => true,
        ]);

        $this->actingAs($admin, 'web')
            ->get('/support/conversations')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Support/chatConversations/Index')
                ->has('conversations', 1)
                ->where('conversations.0.id', $currentConversation->id)
                ->where('conversations.0.status', ChatConversationStatus::Open->value)
                ->where('conversations.0.unread_count', 0)
                ->where('selectedConversation.id', $currentConversation->id)
                ->has('selectedConversation.messages', 2)
                ->where('selectedConversation.messages.0.id', $closedMessage->id)
                ->where('selectedConversation.messages.0.message', 'Message from the closed conversation')
                ->where('selectedConversation.messages.1.message', 'Current conversation reply'));

        $this->assertDatabaseHas('chat_messages', [
            'id' => $closedMessage->id,
            'is_read' => true,
        ]);

        $this->actingAs($admin, 'web')
            ->get('/support/conversations?status=closed')
            ->assertInertia(fn (Assert $page) => $page->has('conversations', 0));
    }
}
