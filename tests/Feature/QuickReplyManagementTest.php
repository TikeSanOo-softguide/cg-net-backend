<?php

namespace Tests\Feature;

use App\Enums\ChatConversationStatus;
use App\Models\Admin;
use App\Models\ChatConversation;
use App\Models\QuickReply;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QuickReplyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_quick_replies(): void
    {
        $this->get('/support/quick-replies')->assertRedirect('/login');
    }

    public function test_admins_without_support_permission_are_forbidden(): void
    {
        $this->autoGrantPermissions = false;
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'web')->get('/support/quick-replies')->assertForbidden();
    }

    public function test_support_admin_can_search_and_filter_quick_replies(): void
    {
        $admin = Admin::factory()->create();
        $this->reply(['keyword' => 'Fiber down', 'response_en' => 'Restart the modem']);
        $this->reply(['keyword' => 'Bill paid', 'category' => 'payment']);

        $this->actingAs($admin, 'web')
            ->get('/support/quick-replies?search=modem&status=active&category=internet')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Support/quickReplies/Index')
                    ->has('items.data', 1)
                    ->where('items.data.0.keyword', 'Fiber down')
                    ->where('filters.category', 'internet')
                    ->where('filters.sort', 'updated_at'),
            );
    }

    public function test_store_validates_and_rejects_duplicate_keywords(): void
    {
        $admin = Admin::factory()->create();
        $this->reply(['keyword' => 'Fiber down']);

        $this->actingAs($admin, 'web')
            ->from('/support/quick-replies')
            ->post('/support/quick-replies/replies', [])
            ->assertRedirect('/support/quick-replies')
            ->assertSessionHasErrors(['keyword', 'category', 'response_en', 'response_my', 'response_zh']);

        $this->actingAs($admin, 'web')
            ->from('/support/quick-replies')
            ->post('/support/quick-replies/replies', $this->payload(['keyword' => '  Fiber down  ']))
            ->assertRedirect('/support/quick-replies')
            ->assertSessionHasErrors('keyword');

        $this->assertDatabaseCount('quick_replies', 1);
    }

    public function test_admin_can_create_update_and_delete_a_quick_reply(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'web')
            ->post('/support/quick-replies/replies', $this->payload())
            ->assertRedirect(route('support.quick-replies.index'))
            ->assertSessionHas('success', 'support.quick_replies.created');

        $reply = QuickReply::query()->firstOrFail();
        $this->assertSame('Account help', $reply->keyword);

        $this->actingAs($admin, 'web')
            ->put("/support/quick-replies/replies/{$reply->id}", $this->payload([
                'keyword' => 'Account help',
                'response_en' => 'Updated English reply',
            ]))
            ->assertRedirect(route('support.quick-replies.index'))
            ->assertSessionHas('success', 'support.quick_replies.updated');

        $reply->refresh();
        $this->assertSame('Updated English reply', $reply->response_en);

        $this->actingAs($admin, 'web')
            ->delete("/support/quick-replies/replies/{$reply->id}")
            ->assertRedirect(route('support.quick-replies.index'))
            ->assertSessionHas('success', 'support.quick_replies.deleted');

        $this->assertSoftDeleted('quick_replies', ['id' => $reply->id]);

        $this->actingAs($admin, 'web')
            ->post('/support/quick-replies/replies', $this->payload())
            ->assertSessionHas('success', 'support.quick_replies.created');
    }

    public function test_admin_can_create_a_closing_quick_reply(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'web')
            ->post('/support/quick-replies/replies', $this->payload([
                'keyword' => 'Conversation closed',
                'category' => 'closing',
            ]))
            ->assertRedirect(route('support.quick-replies.index'))
            ->assertSessionHas('success', 'support.quick_replies.created');

        $this->assertDatabaseHas('quick_replies', [
            'keyword' => 'Conversation closed',
            'category' => 'closing',
        ]);
    }

    public function test_admin_can_bulk_delete_quick_replies(): void
    {
        $admin = Admin::factory()->create();
        $first = $this->reply(['keyword' => 'One']);
        $second = $this->reply(['keyword' => 'Two']);

        $this->actingAs($admin, 'web')
            ->delete('/support/quick-replies/bulk-destroy', ['ids' => [$first->id, $second->id]])
            ->assertRedirect(route('support.quick-replies.index'))
            ->assertSessionHas('success', 'support.quick_replies.bulk_deleted');

        $this->assertSoftDeleted('quick_replies', ['id' => $first->id]);
        $this->assertSoftDeleted('quick_replies', ['id' => $second->id]);
    }

    public function test_chat_panel_hides_deleted_replies_and_inserts_the_requested_language(): void
    {
        $admin = Admin::factory()->create();
        $visible = $this->reply(['keyword' => 'Visible']);
        $hidden = $this->reply(['keyword' => 'Hidden']);
        $hidden->delete();
        $conversation = ChatConversation::query()->create([
            'status' => ChatConversationStatus::Open,
        ]);

        $this->actingAs($admin, 'web')
            ->get('/support/conversations')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->has('quickReplies', 1)
                    ->where('quickReplies.0.keyword', 'Visible')
                    ->where('quickReplyCategories', ['welcome', 'internet', 'payment', 'package', 'technical', 'account', 'closing']),
            );

        $this->actingAs($admin, 'web')
            ->postJson("/support/conversations/{$conversation->id}/quick-replies/{$visible->id}", [
                'language' => 'my',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Myanmar reply');

        $this->actingAs($admin, 'web')
            ->postJson("/support/conversations/{$conversation->id}/quick-replies/{$hidden->id}", [
                'language' => 'zh',
            ])
            ->assertNotFound();
    }

    public function test_admin_can_send_closing_quick_reply_and_close_conversation_atomically(): void
    {
        $admin = Admin::factory()->create();
        $conversation = ChatConversation::query()->create([
            'status' => ChatConversationStatus::WaitingAgent,
        ]);

        $this->actingAs($admin, 'web')
            ->from('/support/conversations')
            ->post("/support/conversations/{$conversation->id}/close", [
                'message' => 'Thank you for contacting support.',
            ])
            ->assertRedirect('/support/conversations');

        $this->assertDatabaseHas('chat_conversations', [
            'id' => $conversation->id,
            'status' => ChatConversationStatus::Closed->value,
        ]);
        $this->assertDatabaseHas('chat_messages', [
            'conversation_id' => $conversation->id,
            'sender_type' => 'agent',
            'message' => 'Thank you for contacting support.',
            'is_read' => true,
        ]);
    }

    public function test_admin_cannot_close_conversation_without_a_final_message(): void
    {
        $admin = Admin::factory()->create();
        $conversation = ChatConversation::query()->create([
            'status' => ChatConversationStatus::Open,
        ]);

        $this->actingAs($admin, 'web')
            ->from('/support/conversations')
            ->post("/support/conversations/{$conversation->id}/close", ['message' => ''])
            ->assertRedirect('/support/conversations')
            ->assertSessionHasErrors('message');

        $this->assertDatabaseHas('chat_conversations', [
            'id' => $conversation->id,
            'status' => ChatConversationStatus::Open->value,
        ]);
        $this->assertDatabaseMissing('chat_messages', [
            'conversation_id' => $conversation->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function reply(array $overrides = []): QuickReply
    {
        return QuickReply::query()->create($this->payload($overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'keyword' => 'Account help',
            'category' => 'internet',
            'response_en' => 'English reply',
            'response_my' => 'Myanmar reply',
            'response_zh' => 'Chinese reply',
        ], $overrides);
    }
}
