<?php

namespace Tests\Feature;

use App\Enums\ChatConversationStatus;
use App\Enums\ChatFlowOptionAction;
use App\Events\ChatConversationStatusChanged;
use App\Events\ChatMessageCreated;
use App\Models\Admin;
use App\Models\ChatConversation;
use App\Models\ChatFlowOption;
use App\Models\ChatFlowStep;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatConversationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private ChatFlowStep $startStep;

    private ChatFlowStep $secondStep;

    private ChatFlowOption $goToStepOption;

    private ChatFlowOption $talkToAdminOption;

    private int $telegramStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.chat_id' => 'test-chat-id',
        ]);

        Http::fake([
            'https://api.telegram.org/*' => fn () => Http::response(
                ['ok' => $this->telegramStatus === 200],
                $this->telegramStatus,
            ),
        ]);

        $this->startStep = ChatFlowStep::query()->create([
            'name' => 'Main Menu',
            'message_en' => 'How can we help you?',
            'message_my' => 'Main menu (my)',
            'message_zh' => 'Main menu (zh)',
            'is_start' => true,
        ]);
        $this->secondStep = ChatFlowStep::query()->create([
            'name' => 'Internet',
            'message_en' => 'Tell us your issue.',
            'message_my' => 'Internet (my)',
            'message_zh' => 'Internet (zh)',
            'is_start' => false,
        ]);
        $this->goToStepOption = ChatFlowOption::query()->create([
            'step_id' => $this->startStep->id,
            'option_en' => 'Internet',
            'option_my' => 'Internet (my)',
            'option_zh' => 'Internet (zh)',
            'action' => ChatFlowOptionAction::GoToStep->value,
            'next_step_id' => $this->secondStep->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $this->talkToAdminOption = ChatFlowOption::query()->create([
            'step_id' => $this->startStep->id,
            'option_en' => 'Talk to Admin',
            'option_my' => 'Talk to Admin (my)',
            'option_zh' => 'Talk to Admin (zh)',
            'action' => ChatFlowOptionAction::TransferAgent->value,
            'reply_text_en' => 'Please wait for an agent.',
            'is_active' => true,
            'sort_order' => 2,
        ]);
    }

    public function test_start_creates_a_chat_flow_conversation_when_none_exists(): void
    {
        $user = User::factory()->create();

        $response = $this->customer($user)->postJson('/api/chat/start')
            ->assertOk()
            ->assertJsonPath('data.status', 'bot')
            ->assertJsonPath('data.step.id', $this->startStep->id)
            ->assertJsonPath('data.can_select_option', true)
            ->assertJsonPath('data.can_send_message', false)
            ->assertJsonPath('data.is_closed', false)
            ->assertJsonPath('data.can_start_new_conversation', false)
            ->assertJsonPath('data.messages.0.sender_type', 'system')
            ->assertJsonPath('data.messages.0.message', 'How can we help you?');

        $this->assertDatabaseCount('chat_conversations', 1);
        $this->assertDatabaseHas('chat_conversations', [
            'id' => $response->json('data.conversation_id'),
            'user_id' => $user->id,
            'status' => 'bot',
            'current_step_id' => $this->startStep->id,
            'agent_id' => null,
        ]);
    }

    public function test_start_returns_existing_active_conversation_without_resetting_it(): void
    {
        $user = User::factory()->create();

        foreach ([ChatConversationStatus::Bot, ChatConversationStatus::WaitingAgent, ChatConversationStatus::Open] as $status) {
            ChatConversation::query()->delete();
            $conversation = $this->conversation($user, $status, ['current_step_id' => $this->secondStep->id]);

            $this->customer($user)->postJson('/api/chat/start')
                ->assertOk()
                ->assertJsonPath('data.conversation_id', $conversation->id)
                ->assertJsonPath('data.status', $status->value);

            $this->assertSame(1, ChatConversation::query()->count());
            $this->assertSame($this->secondStep->id, $conversation->fresh()->current_step_id);
            $this->assertSame(1, $conversation->messages()->count());
        }
    }

    public function test_repeated_start_requests_do_not_create_duplicate_active_conversations(): void
    {
        $user = User::factory()->create();

        $ids = collect(range(1, 5))->map(
            fn () => $this->customer($user)->postJson('/api/chat/start')->assertOk()->json('data.conversation_id'),
        );

        $this->assertCount(1, $ids->unique());
        $this->assertSame(1, $user->chatConversations()->active()->count());
        $this->assertSame(1, $user->chatConversations()->active()->first()->messages()->count());
    }

    public function test_start_after_closed_creates_a_new_conversation_and_leaves_the_old_one_untouched(): void
    {
        $user = User::factory()->create();
        $admin = Admin::factory()->create();
        $closed = $this->conversation($user, ChatConversationStatus::Closed, [
            'agent_id' => $admin->id,
            'current_step_id' => $this->secondStep->id,
        ]);
        $closed->messages()->create(['sender_type' => 'customer', 'message' => 'Old message', 'is_read' => true]);
        $before = $closed->fresh()->only(['status', 'agent_id', 'current_step_id', 'updated_at']);

        $response = $this->customer($user)->postJson('/api/chat/start')
            ->assertOk()
            ->assertJsonPath('data.status', 'bot')
            ->assertJsonPath('data.agent', null)
            ->assertJsonPath('data.step.id', $this->startStep->id)
            ->assertJsonPath('data.messages.0.message', 'Old message')
            ->assertJsonPath('data.messages.1.message', 'How can we help you?');

        $newId = $response->json('data.conversation_id');
        $this->assertNotSame($closed->id, $newId);

        $new = ChatConversation::query()->findOrFail($newId);
        $this->assertNull($new->agent_id);
        $this->assertSame($this->startStep->id, $new->current_step_id);
        $this->assertSame(1, $new->messages()->count());

        $this->assertEquals($before, $closed->fresh()->only(['status', 'agent_id', 'current_step_id', 'updated_at']));
        $this->assertSame(1, $closed->messages()->count());
    }

    public function test_current_returns_state_for_each_status_without_restarting_chat_flow(): void
    {
        $user = User::factory()->create();

        $this->customer($user)->getJson('/api/chat/current')
            ->assertOk()
            ->assertJsonPath('data.conversation_id', null)
            ->assertJsonPath('data.can_start_new_conversation', true);
        $this->assertDatabaseCount('chat_conversations', 0);

        $conversation = $this->conversation($user, ChatConversationStatus::Bot, ['current_step_id' => $this->secondStep->id]);
        $this->customer($user)->getJson('/api/chat/current')
            ->assertOk()
            ->assertJsonPath('data.status', 'bot')
            ->assertJsonPath('data.step.id', $this->secondStep->id);

        $conversation->update(['status' => ChatConversationStatus::WaitingAgent]);
        $this->customer($user)->getJson('/api/chat/current')
            ->assertOk()
            ->assertJsonPath('data.status', 'waiting_agent')
            ->assertJsonPath('data.step', null)
            ->assertJsonPath('data.can_select_option', false)
            ->assertJsonPath('data.can_send_message', false);

        $conversation->update(['status' => ChatConversationStatus::Open, 'agent_id' => Admin::factory()->create()->id]);
        $this->customer($user)->getJson('/api/chat/current')
            ->assertOk()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.step', null)
            ->assertJsonPath('data.can_send_message', true);

        $conversation->update(['status' => ChatConversationStatus::Closed]);
        $conversation->messages()->create(['sender_type' => 'agent', 'message' => 'Bye', 'is_read' => true]);

        $this->customer($user)->getJson('/api/chat/current')
            ->assertOk()
            ->assertJsonPath('data.conversation_id', $conversation->id)
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.messages.1.message', 'Bye');

        $this->customer($user)->getJson('/api/chat/current?conversation_id=' . $conversation->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.step', null)
            ->assertJsonPath('data.is_closed', true)
            ->assertJsonPath('data.can_send_message', false)
            ->assertJsonPath('data.can_select_option', false)
            ->assertJsonPath('data.can_start_new_conversation', true)
            ->assertJsonPath('data.messages.1.message', 'Bye');

        $this->assertDatabaseCount('chat_conversations', 1);
        $this->assertSame(ChatConversationStatus::Closed, $conversation->fresh()->status);
    }

    public function test_customer_cannot_send_message_during_chat_flow(): void
    {
        $this->assertMessageRejected(ChatConversationStatus::Bot);
    }

    public function test_customer_cannot_send_message_while_waiting(): void
    {
        $this->assertMessageRejected(ChatConversationStatus::WaitingAgent);
    }

    public function test_customer_cannot_send_message_to_closed_conversation(): void
    {
        $this->assertMessageRejected(ChatConversationStatus::Closed)
            ->assertJsonPath('message', 'Conversation is closed. Please start a new conversation.')
            ->assertJsonPath('data.can_start_new_conversation', true);
    }

    public function test_customer_can_send_message_during_live_chat(): void
    {
        Event::fake([ChatMessageCreated::class]);
        $user = User::factory()->create();
        $conversation = $this->conversation($user, ChatConversationStatus::Open, ['agent_id' => Admin::factory()->create()->id]);

        $this->customer($user)->postJson('/api/chat/messages', [
            'conversation_id' => $conversation->id,
            'message' => 'Hello agent',
        ])
            ->assertCreated()
            ->assertJsonPath('data.conversation_id', $conversation->id)
            ->assertJsonPath('data.message.message', 'Hello agent')
            ->assertJsonPath('data.message.sender_type', 'customer')
            ->assertJsonMissingPath('data.message.client_message_id');

        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'sender_type' => 'customer',
            'message' => 'Hello agent',
        ]);
        Event::assertDispatched(ChatMessageCreated::class, fn ($event) => $event->message->message === 'Hello agent');
    }

    public function test_message_content_is_validated(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversation($user, ChatConversationStatus::Open);

        $this->customer($user)->postJson('/api/chat/messages', ['conversation_id' => $conversation->id, 'message' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');

        $this->customer($user)->postJson('/api/chat/messages', [
            'conversation_id' => $conversation->id,
            'message' => str_repeat('a', 2001),
        ])->assertUnprocessable();
    }

    public function test_each_customer_message_is_saved_without_a_client_message_id(): void
    {
        Event::fake([ChatMessageCreated::class]);
        $user = User::factory()->create();
        $conversation = $this->conversation($user, ChatConversationStatus::Open);
        $payload = ['conversation_id' => $conversation->id, 'message' => 'Hi'];

        $firstId = $this->customer($user)->postJson('/api/chat/messages', $payload)->assertCreated()->json('data.message.id');
        $secondId = $this->customer($user)->postJson('/api/chat/messages', $payload)->assertCreated()->json('data.message.id');

        $this->assertNotSame($firstId, $secondId);
        $this->assertSame(2, $conversation->messages()->count());
        Event::assertDispatchedTimes(ChatMessageCreated::class, 2);
    }

    public function test_customer_cannot_select_option_while_waiting(): void
    {
        $this->assertOptionRejected(ChatConversationStatus::WaitingAgent);
    }

    public function test_customer_cannot_select_option_during_live_chat(): void
    {
        $this->assertOptionRejected(ChatConversationStatus::Open);
    }

    public function test_customer_cannot_select_option_for_closed_conversation(): void
    {
        $this->assertOptionRejected(ChatConversationStatus::Closed);
    }

    public function test_option_must_belong_to_current_step(): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversation($user, ChatConversationStatus::Bot, ['current_step_id' => $this->secondStep->id]);

        $this->customer($user)->postJson('/api/chat/select-option', [
            'conversation_id' => $conversation->id,
            'option_id' => $this->goToStepOption->id,
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'stale_chat_option')
            ->assertJsonPath('data.step.id', $this->secondStep->id);
    }

    public function test_option_selection_persists_and_broadcasts_customer_and_next_step_in_order(): void
    {
        Event::fake([ChatMessageCreated::class]);
        $user = User::factory()->create();
        $conversation = $this->conversation($user, ChatConversationStatus::Bot);

        $this->customer($user)->postJson('/api/chat/select-option', [
            'conversation_id' => $conversation->id,
            'option_id' => $this->goToStepOption->id,
            'language' => 'en',
        ])
            ->assertOk()
            ->assertJsonPath('data.messages.0.message', 'Internet')
            ->assertJsonPath('data.messages.0.sender_type', 'customer')
            ->assertJsonPath('data.messages.0.flow_options.0.label', 'Internet')
            ->assertJsonPath('data.messages.0.flow_options.1.label', 'Talk to Admin')
            ->assertJsonPath('data.messages.1.message', 'Tell us your issue.')
            ->assertJsonPath('data.messages.1.sender_type', 'system')
            ->assertJsonPath('data.step.id', $this->secondStep->id);

        $messages = Event::dispatched(ChatMessageCreated::class)
            ->map(fn (ChatMessageCreated $event) => $event->message->message)
            ->all();

        $this->assertSame(['Internet', 'Tell us your issue.'], $messages);
        $this->assertSame(2, $conversation->messages()->count());
    }

    public function test_reopening_current_returns_all_flow_history_without_duplicate_start_message(): void
    {
        $user = User::factory()->create();
        $conversation = $this->customer($user)->postJson('/api/chat/start')
            ->assertOk()
            ->json('data.conversation_id');
        $this->customer($user)->postJson('/api/chat/select-option', [
            'conversation_id' => $conversation,
            'option_id' => $this->goToStepOption->id,
        ])->assertOk();

        $this->customer($user)->getJson('/api/chat/current')
            ->assertOk()
            ->assertJsonCount(3, 'data.messages')
            ->assertJsonPath('data.messages.0.message', 'How can we help you?')
            ->assertJsonPath('data.messages.1.message', 'Internet')
            ->assertJsonPath('data.messages.1.flow_options.0.label', 'Internet')
            ->assertJsonPath('data.messages.1.flow_options.1.label', 'Talk to Admin')
            ->assertJsonPath('data.messages.2.message', 'Tell us your issue.');
    }

    public function test_talk_to_admin_moves_chat_flow_to_waiting_and_notifies_telegram_once(): void
    {
        Event::fake([ChatConversationStatusChanged::class]);
        $user = User::factory()->create();
        $conversation = $this->conversation($user, ChatConversationStatus::Bot);

        $this->selectTalkToAdmin($user, $conversation)
            ->assertOk()
            ->assertJsonPath('data.status', 'waiting_agent')
            ->assertJsonPath('data.can_select_option', false)
            ->assertJsonPath('data.can_send_message', false);

        $this->assertSame(ChatConversationStatus::WaitingAgent, $conversation->fresh()->status);
        $this->assertSame(1, ChatConversation::query()->count());
        Http::assertSentCount(1);
        Event::assertDispatched(
            ChatConversationStatusChanged::class,
            fn ($event) => $event->conversation->id === $conversation->id
                && $event->previousStatus === ChatConversationStatus::Bot
                && $event->conversation->status === ChatConversationStatus::WaitingAgent,
        );

        $this->selectTalkToAdmin($user, $conversation)->assertStatus(409);
        $this->customer($user)->getJson('/api/chat/current')->assertOk();
        $this->customer($user)->postJson('/api/chat/start')->assertOk()->assertJsonPath('data.conversation_id', $conversation->id);

        Http::assertSentCount(1);
    }

    public function test_transfer_agent_fallback_reply_uses_the_requested_language(): void
    {
        $this->talkToAdminOption->update([
            'reply_text_en' => null,
            'reply_text_my' => null,
            'reply_text_zh' => null,
        ]);
        $user = User::factory()->create();
        $conversation = $this->conversation($user, ChatConversationStatus::Bot);

        $this->customer($user)->postJson('/api/chat/select-option', [
            'conversation_id' => $conversation->id,
            'option_id' => $this->talkToAdminOption->id,
            'language' => 'my',
        ])
            ->assertOk()
            ->assertJsonPath('data.message', 'အေးဂျင့်တစ်ဦးနှင့် ချိတ်ဆက်ရန် စောင့်ဆိုင်းနေပါသည်။')
            ->assertJsonPath('data.messages.1.message', 'အေးဂျင့်တစ်ဦးနှင့် ချိတ်ဆက်ရန် စောင့်ဆိုင်းနေပါသည်။')
            ->assertJsonPath('data.messages.1.language', 'my');
    }

    public function test_close_chat_fallback_reply_uses_the_requested_language(): void
    {
        $closeOption = ChatFlowOption::query()->create([
            'step_id' => $this->startStep->id,
            'option_en' => 'Close',
            'option_my' => 'Close (my)',
            'option_zh' => 'Close (zh)',
            'action' => ChatFlowOptionAction::CloseChat->value,
            'is_active' => true,
            'sort_order' => 3,
        ]);
        $user = User::factory()->create();
        $conversation = $this->conversation($user, ChatConversationStatus::Bot);

        $this->customer($user)->postJson('/api/chat/select-option', [
            'conversation_id' => $conversation->id,
            'option_id' => $closeOption->id,
            'language' => 'zh',
        ])
            ->assertOk()
            ->assertJsonPath('data.message', '此对话已关闭。')
            ->assertJsonPath('data.messages.1.message', '此对话已关闭。')
            ->assertJsonPath('data.messages.1.language', 'zh');
    }

    public function test_telegram_failure_does_not_prevent_waiting_state(): void
    {
        $this->telegramStatus = 500;
        $user = User::factory()->create();
        $conversation = $this->conversation($user, ChatConversationStatus::Bot);

        $this->selectTalkToAdmin($user, $conversation)
            ->assertOk()
            ->assertJsonPath('data.status', 'waiting_agent');

        $this->assertSame(ChatConversationStatus::WaitingAgent, $conversation->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_admin_accept_moves_waiting_to_open_and_assigns_agent(): void
    {
        Event::fake([ChatConversationStatusChanged::class, ChatMessageCreated::class]);
        $admin = Admin::factory()->create();
        $conversation = $this->conversation(User::factory()->create(), ChatConversationStatus::WaitingAgent);

        $this->actingAs($admin, 'web')
            ->from('/support/conversations')
            ->post("/support/conversations/{$conversation->id}/accept")
            ->assertRedirect('/support/conversations')
            ->assertSessionHasNoErrors();

        $conversation->refresh();
        $this->assertSame(ChatConversationStatus::Open, $conversation->status);
        $this->assertSame($admin->id, $conversation->agent_id);
        $this->assertSame(1, ChatConversation::query()->count());
        Event::assertDispatched(
            ChatConversationStatusChanged::class,
            fn ($event) => $event->conversation->status === ChatConversationStatus::Open
                && $event->previousStatus === ChatConversationStatus::WaitingAgent,
        );
        Event::assertDispatched(ChatMessageCreated::class);
    }

    public function test_admin_cannot_accept_conversation_still_in_chat_flow(): void
    {
        $admin = Admin::factory()->create();
        $conversation = $this->conversation(User::factory()->create(), ChatConversationStatus::Bot);

        $this->actingAs($admin, 'web')
            ->from('/support/conversations')
            ->post("/support/conversations/{$conversation->id}/accept")
            ->assertSessionHasErrors('conversation');

        $this->assertSame(ChatConversationStatus::Bot, $conversation->fresh()->status);
    }

    public function test_admin_cannot_reply_before_accepting(): void
    {
        $admin = Admin::factory()->create();
        $conversation = $this->conversation(User::factory()->create(), ChatConversationStatus::WaitingAgent);

        $this->actingAs($admin, 'web')
            ->from('/support/conversations')
            ->post("/support/conversations/{$conversation->id}/messages", ['message' => 'Hi'])
            ->assertSessionHasErrors('conversation');

        $this->assertSame(0, $conversation->messages()->count());
    }

    public function test_admin_reply_in_live_chat_is_broadcast(): void
    {
        Event::fake([ChatMessageCreated::class]);
        $admin = Admin::factory()->create();
        $conversation = $this->conversation(User::factory()->create(), ChatConversationStatus::Open, ['agent_id' => $admin->id]);

        $this->actingAs($admin, 'web')
            ->from('/support/conversations')
            ->post("/support/conversations/{$conversation->id}/messages", ['message' => 'How can I help?'])
            ->assertSessionHasNoErrors();

        Event::assertDispatched(
            ChatMessageCreated::class,
            fn ($event) => $event->message->message === 'How can I help?'
                && $event->broadcastOn()[0]->name === 'private-chat.conversation.' . $conversation->id,
        );
    }

    public function test_admin_close_moves_open_to_closed_and_broadcasts(): void
    {
        Event::fake([ChatConversationStatusChanged::class, ChatMessageCreated::class]);
        $admin = Admin::factory()->create();
        $user = User::factory()->create();
        $conversation = $this->conversation($user, ChatConversationStatus::Open, ['agent_id' => $admin->id]);

        $this->actingAs($admin, 'web')
            ->from('/support/conversations')
            ->post("/support/conversations/{$conversation->id}/close", ['message' => 'Goodbye'])
            ->assertSessionHasNoErrors();

        $this->assertSame(ChatConversationStatus::Closed, $conversation->fresh()->status);
        Event::assertDispatched(
            ChatConversationStatusChanged::class,
            fn ($event) => $event->conversation->status === ChatConversationStatus::Closed
                && $event->broadcastWith()['is_closed'] === true
                && $event->broadcastWith()['can_start_new_conversation'] === true,
        );

        $this->customer($user)->postJson('/api/chat/messages', [
            'conversation_id' => $conversation->id,
            'message' => 'One more thing',
        ])->assertStatus(409);
    }

    public function test_closed_conversation_cannot_be_reopened(): void
    {
        $admin = Admin::factory()->create();
        $conversation = $this->conversation(User::factory()->create(), ChatConversationStatus::Closed, ['agent_id' => $admin->id]);

        foreach (['open', 'waiting_agent'] as $status) {
            $this->actingAs($admin, 'web')
                ->from('/support/conversations')
                ->put("/support/conversations/{$conversation->id}/status", ['status' => $status])
                ->assertSessionHasErrors('conversation');
        }

        $this->actingAs($admin, 'web')
            ->from('/support/conversations')
            ->post("/support/conversations/{$conversation->id}/accept")
            ->assertSessionHasErrors('conversation');

        $this->actingAs($admin, 'web')
            ->from('/support/conversations')
            ->post("/support/conversations/{$conversation->id}/messages", ['message' => 'Hi'])
            ->assertSessionHasErrors('conversation');

        $this->assertSame(ChatConversationStatus::Closed, $conversation->fresh()->status);
        $this->assertSame(0, $conversation->messages()->count());
    }

    public function test_customer_cannot_access_another_customers_conversation(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $bot = $this->conversation($owner, ChatConversationStatus::Bot);
        $live = $this->conversation(User::factory()->create(), ChatConversationStatus::Open);

        $this->customer($intruder)->getJson('/api/chat/current?conversation_id=' . $bot->id)->assertForbidden();

        $this->customer($intruder)->postJson('/api/chat/select-option', [
            'conversation_id' => $bot->id,
            'option_id' => $this->goToStepOption->id,
        ])->assertForbidden();

        $this->customer($intruder)->postJson('/api/chat/messages', [
            'conversation_id' => $live->id,
            'message' => 'Hijack',
        ])->assertForbidden();

        $this->customer($intruder)->postJson('/api/chat/start')
            ->assertOk()
            ->assertJsonPath('data.status', 'bot');

        $this->assertSame($this->startStep->id, $bot->fresh()->current_step_id);
        $this->assertSame(0, $live->messages()->count());
        $this->assertNotSame($bot->id, $intruder->chatConversations()->value('id'));
    }

    public function test_customer_can_only_subscribe_to_own_conversation_channel(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
            'broadcasting.connections.reverb.options.host' => 'localhost',
        ]);
        Broadcast::forgetDrivers();
        require base_path('routes/channels.php');

        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $conversation = $this->conversation($owner, ChatConversationStatus::Open);
        $payload = ['socket_id' => '1234.5678', 'channel_name' => 'private-chat.conversation.' . $conversation->id];

        $this->customer($owner)->postJson('/api/broadcasting/auth', $payload)
            ->assertOk()
            ->assertJsonStructure(['auth']);

        $this->customer($intruder)->postJson('/api/broadcasting/auth', $payload)->assertForbidden();
    }

    private function assertMessageRejected(ChatConversationStatus $status): \Illuminate\Testing\TestResponse
    {
        Event::fake([ChatMessageCreated::class]);
        $user = User::factory()->create();
        $conversation = $this->conversation($user, $status);

        $response = $this->customer($user)->postJson('/api/chat/messages', [
            'conversation_id' => $conversation->id,
            'message' => 'Free text',
        ])
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.status', $status->value)
            ->assertJsonPath('data.can_send_message', false);

        $this->assertSame(0, $conversation->messages()->count());
        $this->assertSame($status, $conversation->fresh()->status);
        Event::assertNotDispatched(ChatMessageCreated::class);

        return $response;
    }

    private function assertOptionRejected(ChatConversationStatus $status): void
    {
        $user = User::factory()->create();
        $conversation = $this->conversation($user, $status);

        $this->customer($user)->postJson('/api/chat/select-option', [
            'conversation_id' => $conversation->id,
            'option_id' => $this->goToStepOption->id,
        ])
            ->assertStatus(409)
            ->assertJsonPath('data.status', $status->value)
            ->assertJsonPath('data.can_select_option', false);

        $conversation->refresh();
        $this->assertSame($status, $conversation->status);
        $this->assertSame($this->startStep->id, $conversation->current_step_id);
        $this->assertSame(0, $conversation->messages()->count());
        Http::assertSentCount(0);
    }

    private function selectTalkToAdmin(User $user, ChatConversation $conversation): \Illuminate\Testing\TestResponse
    {
        return $this->customer($user)->postJson('/api/chat/select-option', [
            'conversation_id' => $conversation->id,
            'option_id' => $this->talkToAdminOption->id,
        ]);
    }

    private function conversation(User $user, ChatConversationStatus $status, array $attributes = []): ChatConversation
    {
        return ChatConversation::factory()->create(array_merge([
            'user_id' => $user->id,
            'agent_id' => null,
            'current_step_id' => $this->startStep->id,
            'status' => $status,
        ], $attributes));
    }

    private function customer(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('chat')->plainTextToken);
    }
}
