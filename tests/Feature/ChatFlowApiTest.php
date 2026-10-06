<?php

namespace Tests\Feature;

use App\Enums\ChatConversationStatus;
use App\Enums\ChatFlowOptionAction;
use App\Models\ChatFlowOption;
use App\Models\ChatFlowStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatFlowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_chat_flow_requires_authentication(): void
    {
        $this->postJson('/api/chat-flow/start')->assertUnauthorized();
    }

    public function test_customer_can_start_chat_flow_with_supported_language(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $startStep = ChatFlowStep::query()->create([
            'name' => 'Main Menu',
            'message_en' => 'How can we help you?',
            'message_my' => 'မင်္ဂလာပါ၊ မင်္ဂလာနဲ့ဘာလုပ်ပေးနိုင်မလဲ။',
            'message_zh' => '您好，我们能为您提供什么帮助？',
            'is_start' => true,
        ]);
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

        $this->withToken($user->createToken('chat')->plainTextToken)
            ->postJson('/api/chat-flow/start', ['language' => 'en'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.step.message', 'How can we help you?')
            ->assertJsonPath('data.step.options.0.label', 'Internet');

        $this->assertDatabaseHas('chat_conversations', [
            'user_id' => $user->id,
            'current_step_id' => $startStep->id,
            'status' => ChatConversationStatus::Open->value,
        ]);
    }

    public function test_invalid_language_is_rejected(): void
    {
        Http::fake();

        $user = User::factory()->create();
        ChatFlowStep::query()->create([
            'name' => 'Main Menu',
            'message_en' => 'How can we help you?',
            'message_my' => 'မင်္ဂလာပါ၊ မင်္ဂလာနဲ့ဘာလုပ်ပေးနိုင်မလဲ။',
            'message_zh' => '您好，我们能为您提供什么帮助？',
            'is_start' => true,
        ]);

        $this->withToken($user->createToken('chat')->plainTextToken)
            ->postJson('/api/chat-flow/start', ['language' => 'fr'])
            ->assertStatus(422);
    }

    public function test_customer_can_select_a_go_to_step_option(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $startStep = ChatFlowStep::query()->create([
            'name' => 'Main Menu',
            'message_en' => 'How can we help you?',
            'message_my' => 'မင်္ဂလာပါ၊ မင်္ဂလာနဲ့ဘာလုပ်ပေးနိုင်မလဲ။',
            'message_zh' => '您好，我们能为您提供什么帮助？',
            'is_start' => true,
        ]);
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
            'status' => ChatConversationStatus::Open,
        ]);

        $this->withToken($user->createToken('chat')->plainTextToken)
            ->postJson('/api/chat-flow/select-option', [
                'conversation_id' => $conversation->id,
                'option_id' => $option->id,
                'language' => 'en',
            ])
            ->assertOk()
            ->assertJsonPath('data.action', 'go_to_step')
            ->assertJsonPath('data.step.message', 'Tell us your issue.');

        $conversation->refresh();
        $this->assertSame($nextStep->id, $conversation->current_step_id);
    }

    public function test_customer_can_select_a_reply_text_option(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $startStep = ChatFlowStep::query()->create([
            'name' => 'Main Menu',
            'message_en' => 'How can we help you?',
            'message_my' => 'မင်္ဂလာပါ၊ မင်္ဂလာနဲ့ဘာလုပ်ပေးနိုင်မလဲ။',
            'message_zh' => '您好，我们能为您提供什么帮助？',
            'is_start' => true,
        ]);
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
            'status' => ChatConversationStatus::Open,
        ]);

        $this->withToken($user->createToken('chat')->plainTextToken)
            ->postJson('/api/chat-flow/select-option', [
                'conversation_id' => $conversation->id,
                'option_id' => $option->id,
                'language' => 'zh',
            ])
            ->assertOk()
            ->assertJsonPath('data.action', 'reply_text')
            ->assertJsonPath('data.reply', '请联系我们的商务团队。');
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
        $startStep = ChatFlowStep::query()->create([
            'name' => 'Main Menu',
            'message_en' => 'How can we help you?',
            'message_my' => 'မင်္ဂလာပါ၊ မင်္ဂလာနဲ့ဘာလုပ်ပေးနိုင်မလဲ။',
            'message_zh' => '您好，我们能为您提供什么帮助？',
            'is_start' => true,
        ]);
        $option = ChatFlowOption::query()->create([
            'step_id' => $startStep->id,
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

        $conversation = $user->chatConversations()->create([
            'current_step_id' => $startStep->id,
            'status' => ChatConversationStatus::Open,
        ]);

        $this->withToken($user->createToken('chat')->plainTextToken)
            ->postJson('/api/chat-flow/select-option', [
                'conversation_id' => $conversation->id,
                'option_id' => $option->id,
                'language' => 'my',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'waiting_agent')
            ->assertJsonPath('data.message', 'ဝန်ထမ်းတစ်ဦးနှင့် ချိတ်ဆက်ပေးနေပါသည်။');

        $conversation->refresh();
        $this->assertSame(ChatConversationStatus::WaitingAgent->value, $conversation->status->value);
        Http::assertSentCount(1);
        Http::assertSent(fn($request) => $request['chat_id'] === 'test-chat-id'
            && str_contains($request['text'], 'Conversation: #' . $conversation->id)
            && str_contains($request['text'], 'Reason: ' . $option->option_my));
    }

    public function test_waiting_conversation_does_not_send_duplicate_telegram_notification(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $user = User::factory()->create();
        $startStep = ChatFlowStep::query()->create([
            'name' => 'Main Menu',
            'message_en' => 'How can we help you?',
            'message_my' => 'မင်္ဂလာပါ၊ မင်္ဂလာနဲ့ဘာလုပ်ပေးနိုင်မလဲ။',
            'message_zh' => '您好，我们能为您提供什么帮助？',
            'is_start' => true,
        ]);
        $option = ChatFlowOption::query()->create([
            'step_id' => $startStep->id,
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

        $conversation = $user->chatConversations()->create([
            'current_step_id' => $startStep->id,
            'status' => ChatConversationStatus::WaitingAgent,
        ]);

        $this->withToken($user->createToken('chat')->plainTextToken)
            ->postJson('/api/chat-flow/select-option', [
                'conversation_id' => $conversation->id,
                'option_id' => $option->id,
                'language' => 'en',
            ])
            ->assertOk();

        Http::assertSentCount(0);
    }
}
