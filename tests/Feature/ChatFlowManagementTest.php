<?php

namespace Tests\Feature;

use App\Enums\ChatFlowOptionAction;
use App\Models\Admin;
use App\Models\ChatFlowOption;
use App\Models\ChatFlowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ChatFlowManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_list_includes_all_steps_and_marks_the_configured_start(): void
    {
        $this->actingAs($this->admin(), 'web');
        $start = $this->step('Main Menu', true);
        $other = $this->step('Internet');

        $this->get('/support/chatbot-flows')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Support/chatFlowsStep/Index')
                ->has('steps', 2)
                ->where('steps.0.id', $start->id)
                ->where('steps.0.is_start', true)
                ->where('steps.1.id', $other->id)
                ->where('steps.1.is_start', false));
    }

    public function test_updating_a_step_replaces_the_start_without_leaving_multiple_start_steps(): void
    {
        $this->actingAs($this->admin(), 'web');
        $start = $this->step('Main Menu', true);
        $replacement = $this->step('Internet');

        $this->put("/support/chatbot-flows/steps/{$replacement->id}", $this->stepData([
            'name' => $replacement->name,
            'is_start' => true,
        ]))->assertRedirect();

        $this->assertDatabaseHas('chat_flow_steps', [
            'id' => $start->id,
            'is_start' => false,
        ]);
        $this->assertDatabaseHas('chat_flow_steps', [
            'id' => $replacement->id,
            'is_start' => true,
        ]);
        $this->assertSame(1, ChatFlowStep::query()->where('is_start', true)->count());
    }

    public function test_the_only_start_step_cannot_be_unset(): void
    {
        $this->actingAs($this->admin(), 'web');
        $start = $this->step('Main Menu', true);

        $this->put("/support/chatbot-flows/steps/{$start->id}", $this->stepData([
            'name' => $start->name,
            'is_start' => false,
        ]))->assertSessionHasErrors('is_start');

        $this->assertDatabaseHas('chat_flow_steps', [
            'id' => $start->id,
            'is_start' => true,
        ]);
    }

    public function test_creating_the_first_step_configures_it_as_the_start(): void
    {
        $this->actingAs($this->admin(), 'web')
            ->post('/support/chatbot-flows/steps', $this->stepData())
            ->assertRedirect();

        $this->assertSame(1, ChatFlowStep::query()->where('is_start', true)->count());
    }

    public function test_step_referenced_by_options_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin(), 'web');
        $start = $this->step('Main Menu', true);
        $destination = $this->step('Internet');
        $option = $this->option($start, $destination);

        $this->delete("/support/chatbot-flows/steps/{$destination->id}")
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('chat_flow_steps', ['id' => $destination->id]);
        $this->assertDatabaseHas('chat_flow_options', [
            'id' => $option->id,
            'next_step_id' => $destination->id,
        ]);
    }

    public function test_step_with_owned_options_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin(), 'web');
        $start = $this->step('Main Menu', true);
        $step = $this->step('Internet');
        $option = $this->option($step, $start);

        $this->delete("/support/chatbot-flows/steps/{$step->id}")
            ->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('chat_flow_options', [
            'id' => $option->id,
            'step_id' => $step->id,
        ]);
    }

    private function admin(): Admin
    {
        $admin = Admin::factory()->create();
        $admin->givePermissionTo([
            Permission::findOrCreate('support.view', 'web'),
            Permission::findOrCreate('support.create', 'web'),
            Permission::findOrCreate('support.update', 'web'),
            Permission::findOrCreate('support.delete', 'web'),
        ]);

        return $admin;
    }

    private function step(string $name, bool $isStart = false): ChatFlowStep
    {
        return ChatFlowStep::query()->create([
            'name' => $name,
            'message_en' => "Message for {$name}",
            'message_my' => "{$name} အတွက် မက်ဆေ့ချ်",
            'message_zh' => "{$name} 的消息",
            'is_start' => $isStart,
        ]);
    }

    private function option(ChatFlowStep $source, ChatFlowStep $destination): ChatFlowOption
    {
        return ChatFlowOption::query()->create([
            'step_id' => $source->id,
            'option_en' => 'Continue',
            'option_my' => 'ဆက်ရန်',
            'option_zh' => '继续',
            'action' => ChatFlowOptionAction::GoToStep->value,
            'next_step_id' => $destination->id,
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function stepData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Chat step',
            'is_start' => false,
            'message_en' => 'English prompt',
            'message_my' => 'မြန်မာစာ',
            'message_zh' => '中文提示',
        ], $overrides);
    }
}
