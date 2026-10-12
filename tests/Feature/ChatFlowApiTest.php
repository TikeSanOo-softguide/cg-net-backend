<?php

namespace Tests\Feature;

use App\Enums\ChatConversationStatus;
use App\Enums\ChatFlowOptionAction;
use App\Events\ChatMessageCreated;
use App\Models\ChatFlowOption;
use App\Models\ChatFlowStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatFlowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_chat_flow_requires_authentication(): void
    {
        $this->postJson('/api/chat/start')->assertUnauthorized();
        $this->getJson('/api/chat/current')->assertUnauthorized();
        $this->postJson('/api/chat/select-option')->assertUnauthorized();
        $this->postJson('/api/chat/messages')->assertUnauthorized();
    }

    public function test_customer_can_start_chat_flow_with_supported_language(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $startStep = $this->startStep();
        ChatFlowOption::query()->create([
            'step_id' => $startStep->id,
            'option_en' => 'Internet',
            'option_my' => 'အင်တာနက်',
            'option_zh' => '互联网',
            'action' => ChatFlowOptionAction::GoToStep->value,
            'next_step_id' => null,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->withToken($user->createToken('chat')->plainTextToken)
            ->postJson('/api/chat/start', ['language' => 'en'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', ChatConversationStatus::Bot->value)
            ->assertJsonPath('data.can_select_option', true)
            ->assertJsonPath('data.can_send_message', false)
            ->assertJsonPath('data.step.message', 'How can we help you?')
            ->assertJsonPath('data.step.options.0.label', 'Internet')
            ->assertJsonPath('data.messages.0.sender_type', 'system')
            ->assertJsonPath('data.messages.0.message', 'How can we help you?');

        $this->assertDatabaseHas('chat_conversations', [
            'user_id' => $user->id,
            'current_step_id' => $startStep->id,
            'status' => ChatConversationStatus::Bot->value,
        ]);
        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $response->json('data.conversation_id'),
            'sender_type' => 'system',
            'message' => 'How can we help you?',
        ]);
    }

    public function test_invalid_language_is_rejected(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $this->startStep();

        $this->withToken($user->createToken('chat')->plainTextToken)
            ->postJson('/api/chat/start', ['language' => 'fr'])
            ->assertStatus(422);
    }

    public function test_customer_can_select_a_go_to_step_option(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $startStep = $this->startStep();
        $nextStep = ChatFlowStep::query()->create([
            'name' => 'Internet',
            'message_en' => 'Tell us your issue.',
            'message_my' => 'သင့်ပြဿနာကို ပြောပါ။',
            'message_zh' => '告诉我们您的问题。',
            'is_start' => false,
        ]);
        $option = ChatFlowOption::query()->create([
            'step_id' => $startStep->id,
            'option_en' => 'Internet',
            'option_my' => 'အင်တာနက်',
            'option_zh' => '互联网',
            'action' => ChatFlowOptionAction::GoToStep->value,
            'next_step_id' => $nextStep->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $conversation = $user->chatConversations()->create([
            'current_step_id' => $startStep->id,
            'status' => ChatConversationStatus::Bot,
        ]);

        $this->withToken($user->createToken('chat')->plainTextToken)
            ->postJson('/api/chat/select-option', [
                'conversation_id' => $conversation->id,
                'option_id' => $option->id,
                'language' => 'en',
            ])
            ->assertOk()
            ->assertJsonPath('data.action', 'go_to_step')
            ->assertJsonPath('data.status', ChatConversationStatus::Bot->value)
            ->assertJsonPath('data.step.message', 'Tell us your issue.');

        $conversation->refresh();
        $this->assertSame($nextStep->id, $conversation->current_step_id);
    }

    public function test_history_messages_keep_step_options_for_each_chat_flow_step(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $startStep = $this->startStep();
        $nextStep = ChatFlowStep::query()->create([
            'name' => 'Issue',
            'message_en' => 'Please select your issue.',
            'message_my' => 'သင့်ပြဿနာကို ရွေးပါ။',
            'message_zh' => '请选择您的问题。',
            'is_start' => false,
        ]);
        $firstOption = ChatFlowOption::query()->create([
            'step_id' => $startStep->id,
            'option_en' => 'Internet',
            'option_my' => 'အင်တာနက်',
            'option_zh' => '互联网',
            'action' => ChatFlowOptionAction::GoToStep->value,
            'next_step_id' => $nextStep->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $issueOption = ChatFlowOption::query()->create([
            'step_id' => $nextStep->id,
            'option_en' => 'Connection',
            'option_my' => 'အဆက်ပြတ်',
            'option_zh' => '连接',
            'action' => ChatFlowOptionAction::ReplyText->value,
            'reply_text_en' => 'We will check your connection.',
            'reply_text_my' => 'ကျွန်ုပ်တို့ ချိတ်ဆက်မှုကို စစ်ဆေးပေးမည်။',
            'reply_text_zh' => '我们会检查您的连接。',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $conversation = $user->chatConversations()->create([
            'current_step_id' => $startStep->id,
            'status' => ChatConversationStatus::Bot,
        ]);

        $this->withToken($user->createToken('chat')->plainTextToken)
            ->postJson('/api/chat/start', ['language' => 'en'])
            ->assertOk();

        $this->withToken($user->createToken('chat')->plainTextToken)
            ->postJson('/api/chat/select-option', [
                'conversation_id' => $conversation->id,
                'option_id' => $firstOption->id,
                'language' => 'en',
            ])
            ->assertOk();

        $this->withToken($user->createToken('chat')->plainTextToken)
            ->getJson('/api/chat/current?conversation_id=' . $conversation->id . '&language=my')
            ->assertOk()
            ->assertJsonPath('data.messages.0.flow_options.0.label', 'Internet')
            ->assertJsonPath('data.messages.1.selected_option.label', 'Internet')
            ->assertJsonPath('data.messages.1.flow_options.0.label', 'Internet')
            ->assertJsonPath('data.messages.2.step.message', 'Please select your issue.')
            ->assertJsonPath('data.messages.2.flow_options.0.label', 'Connection')
            ->assertJsonPath('data.step.message', 'သင့်ပြဿနာကို ရွေးပါ။')
            ->assertJsonPath('data.step.options.0.label', 'အဆက်ပြတ်');

        $this->withToken($user->createToken('chat')->plainTextToken)
            ->postJson('/api/chat/select-option', [
                'conversation_id' => $conversation->id,
                'option_id' => $issueOption->id,
                'language' => 'my',
            ])
            ->assertOk()
            ->assertJsonPath('data.messages.1.selected_option.label', 'Internet')
            ->assertJsonPath('data.messages.1.flow_options.0.label', 'Internet')
            ->assertJsonPath('data.messages.2.flow_options.0.label', 'Connection')
            ->assertJsonPath('data.messages.3.selected_option.label', 'အဆက်ပြတ်')
            ->assertJsonPath('data.messages.4.message', 'ကျွန်ုပ်တို့ ချိတ်ဆက်မှုကို စစ်ဆေးပေးမည်။');
    }

    public function test_customer_can_select_a_reply_text_option(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $startStep = $this->startStep();
        $option = ChatFlowOption::query()->create([
            'step_id' => $startStep->id,
            'option_en' => 'Business',
            'option_my' => 'စီးပွားရေး',
            'option_zh' => '商业',
            'action' => ChatFlowOptionAction::ReplyText->value,
            'reply_text_en' => 'Please contact our business team.',
            'reply_text_my' => 'ကျွန်ုပ်တို့၏စီးပွားရေးအဖွဲ့နှင့်ဆက်သွယ်ပါ။',
            'reply_text_zh' => '请联系我们的商务团队。',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $conversation = $user->chatConversations()->create([
            'current_step_id' => $startStep->id,
            'status' => ChatConversationStatus::Bot,
        ]);

        $this->withToken($user->createToken('chat')->plainTextToken)
            ->postJson('/api/chat/select-option', [
                'conversation_id' => $conversation->id,
                'option_id' => $option->id,
                'language' => 'zh',
            ])
            ->assertOk()
            ->assertJsonPath('data.action', 'reply_text')
            ->assertJsonPath('data.reply', '请联系我们的商务团队。');
    }

    public function test_go_to_url_only_returns_the_url_without_replying_or_changing_the_step(): void
    {
        $user = User::factory()->create();
        $startStep = $this->startStep();
        $option = ChatFlowOption::query()->create([
            'step_id' => $startStep->id,
            'option_en' => 'Visit our website',
            'option_my' => 'ကျွန်ုပ်တို့၏ဝဘ်ဆိုက်သို့ ဝင်ရောက်ပါ',
            'option_zh' => '访问我们的网站',
            'action' => ChatFlowOptionAction::GoToUrl->value,
            'url' => 'https://example.com/support',
            'reply_text_en' => 'This reply must not be shown.',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $token = $user->createToken('chat')->plainTextToken;
        $conversationId = $this->withToken($token)
            ->postJson('/api/chat/start', ['language' => 'en'])
            ->assertOk()
            ->json('data.conversation_id');

        Event::fake([ChatMessageCreated::class]);

        $this->withToken($token)
            ->postJson('/api/chat/select-option', [
                'conversation_id' => $conversationId,
                'option_id' => $option->id,
                'language' => 'en',
            ])
            ->assertOk()
            ->assertJsonPath('data.action', ChatFlowOptionAction::GoToUrl->value)
            ->assertJsonPath('data.url', 'https://example.com/support')
            ->assertJsonMissingPath('data.reply')
            ->assertJsonMissingPath('data.message');

        $this->assertDatabaseHas('chat_conversations', [
            'id' => $conversationId,
            'current_step_id' => $startStep->id,
        ]);
        $this->assertDatabaseCount('chat_messages', 1);
        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversationId,
            'sender_type' => 'system',
            'message' => $startStep->message_en,
        ]);
        Event::assertNotDispatched(ChatMessageCreated::class);

        $this->withToken($token)
            ->postJson('/api/chat/select-option', [
                'conversation_id' => $conversationId,
                'option_id' => $option->id,
                'language' => 'en',
            ])
            ->assertOk()
            ->assertJsonPath('data.action', ChatFlowOptionAction::GoToUrl->value)
            ->assertJsonPath('data.url', 'https://example.com/support');

        $this->withToken($token)
            ->getJson('/api/chat/current?conversation_id=' . $conversationId . '&language=en')
            ->assertOk()
            ->assertJsonCount(1, 'data.messages')
            ->assertJsonPath('data.step.id', $startStep->id);

        $this->assertDatabaseCount('chat_messages', 1);
    }

    public function test_transfer_agent_updates_status_and_sends_telegram_notification_once(): void
    {
        config([
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.chat_id' => 'test-chat-id',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $user = User::factory()->create([
            'name' => 'John Doe',
            'broadband_account_number' => 'CG123456',
        ]);
        $startStep = $this->startStep();
        $option = $this->transferOption($startStep);

        $conversation = $user->chatConversations()->create([
            'current_step_id' => $startStep->id,
            'status' => ChatConversationStatus::Bot,
        ]);

        $this->withToken($user->createToken('chat')->plainTextToken)
            ->postJson('/api/chat/select-option', [
                'conversation_id' => $conversation->id,
                'option_id' => $option->id,
                'language' => 'my',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'waiting_agent')
            ->assertJsonPath('data.can_select_option', false)
            ->assertJsonPath('data.can_send_message', false)
            ->assertJsonPath('data.message', 'ဝန်ထမ်းတစ်ဦးနှင့် ချိတ်ဆက်ပေးနေပါသည်။');

        $conversation->refresh();
        $this->assertSame(ChatConversationStatus::WaitingAgent->value, $conversation->status->value);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['chat_id'] === 'test-chat-id'
            && str_contains($request['text'], 'Conversation: #' . $conversation->id)
            && str_contains($request['text'], 'Reason: ' . $option->option_my));
    }

    public function test_waiting_conversation_rejects_option_and_does_not_send_duplicate_telegram_notification(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $user = User::factory()->create();
        $startStep = $this->startStep();
        $option = $this->transferOption($startStep);

        $conversation = $user->chatConversations()->create([
            'current_step_id' => $startStep->id,
            'status' => ChatConversationStatus::WaitingAgent,
        ]);

        $this->withToken($user->createToken('chat')->plainTextToken)
            ->postJson('/api/chat/select-option', [
                'conversation_id' => $conversation->id,
                'option_id' => $option->id,
                'language' => 'en',
            ])
            ->assertStatus(409)
            ->assertJsonPath('data.status', 'waiting_agent');

        Http::assertSentCount(0);
    }

    private function startStep(): ChatFlowStep
    {
        return ChatFlowStep::query()->create([
            'name' => 'Main Menu',
            'message_en' => 'How can we help you?',
            'message_my' => 'မင်္ဂလာပါ၊ မင်္ဂလာနဲ့ဘာလုပ်ပေးနိုင်မလဲ။',
            'message_zh' => '您好，我们能为您提供什么帮助？',
            'is_start' => true,
        ]);
    }

    private function transferOption(ChatFlowStep $step): ChatFlowOption
    {
        return ChatFlowOption::query()->create([
            'step_id' => $step->id,
            'option_en' => 'Talk to agent',
            'option_my' => 'ဝန်ထမ်းနှင့် စကားပြောမည်',
            'option_zh' => '联系人工客服',
            'action' => ChatFlowOptionAction::TransferAgent->value,
            'reply_text_en' => 'We are connecting you to an agent.',
            'reply_text_my' => 'ဝန်ထမ်းတစ်ဦးနှင့် ချိတ်ဆက်ပေးနေပါသည်။',
            'reply_text_zh' => '我们正在为您接通人工客服。',
            'is_active' => true,
            'sort_order' => 3,
        ]);
    }
}
