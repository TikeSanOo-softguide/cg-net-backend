<?php

namespace App\Http\Controllers\Support\ChatFlows;

use App\Enums\ChatFlowOptionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Support\ChatFlows\StoreChatFlowOptionRequest;
use App\Http\Requests\Support\ChatFlows\StoreChatFlowStepRequest;
use App\Http\Requests\Support\ChatFlows\UpdateChatFlowOptionRequest;
use App\Http\Requests\Support\ChatFlows\UpdateChatFlowStepRequest;
use App\Models\ChatFlowOption;
use App\Models\ChatFlowStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ChatbotFlowsController extends Controller
{
    /**
     * Display the chatbot flow.
     */
    public function index(Request $request): Response
    {
        $steps = ChatFlowStep::query()
            ->with(['options.nextStep'])
            ->get();

        return Inertia::render('Support/chatFlowsStep/Index', [
            'steps' => $steps,
            'actionOptions' => array_map(
                fn(ChatFlowOptionAction $action) => [
                    'value' => $action->value,
                    'label' => str($action->value)->headline()->toString(),
                ],
                ChatFlowOptionAction::cases(),
            ),
        ]);
    }

    /**
     * Create a new chatbot step.
     */
    public function storeStep(StoreChatFlowStepRequest $request)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated) {
            $hasStartStep = ChatFlowStep::query()->where('is_start', true)->exists();
            $isStart = !$hasStartStep || ($validated['is_start'] ?? false);

            if ($isStart) {
                ChatFlowStep::query()
                    ->where('is_start', true)
                    ->update([
                        'is_start' => false,
                    ]);
            }

            ChatFlowStep::create([
                'name' => $validated['name'],
                'message_en' => $validated['message_en'],
                'message_my' => $validated['message_my'],
                'message_zh' => $validated['message_zh'],
                'is_start' => $isStart,
            ]);
        });

        return back()->with('success', 'support.chatbot_flows.step_created');
    }

    /**
     * Update a chatbot step.
     */
    public function updateStep(UpdateChatFlowStepRequest $request, ChatFlowStep $step)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($step, $validated) {
            $isStart = $validated['is_start'] ?? false;
            if ($isStart) {
                ChatFlowStep::query()
                    ->where('id', '!=', $step->id)
                    ->update([
                        'is_start' => false,
                    ]);
            }

            $step->update([
                'name' => $validated['name'],
                'message_en' => $validated['message_en'],
                'message_my' => $validated['message_my'],
                'message_zh' => $validated['message_zh'],
                'is_start' => $isStart,
            ]);
        });

        return back()->with('success', 'support.chatbot_flows.step_updated');
    }

    /**
     * Delete a chatbot step.
     */
    public function destroyStep(ChatFlowStep $step)
    {
        $isUsed = ChatFlowOption::query()->where('next_step_id', $step->id)->exists();

        if ($isUsed) {
            return back()->withErrors(['delete' => 'support.chatbot_flows.step_deleted_failed']);
        }

        $step->delete();

        return back()->with('success', 'support.chatbot_flows.step_deleted');
    }

    /**
     * Create a chatbot option.
     */
    public function storeOption(StoreChatFlowOptionRequest $request, ChatFlowStep $step)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($step, $validated) {
            $step->options()->create([
                'option_en' => $validated['option_en'],
                'option_my' => $validated['option_my'],
                'option_zh' => $validated['option_zh'],
                'action' => $validated['action'],
                'next_step_id' => $validated['next_step_id'] ?? null,
                'url' => $validated['url'] ?? null,
                'reply_text_en' => $validated['reply_text_en'] ?? null,
                'reply_text_my' => $validated['reply_text_my'] ?? null,
                'reply_text_zh' => $validated['reply_text_zh'] ?? null,
                'sort_order' => $validated['sort_order'],
                'is_active' => $validated['is_active'],
            ]);
        });

        return back()->with('success', 'support.chatbot_flows.option_created');
    }

    /**
     * Update a chatbot option.
     */
    public function updateOption(UpdateChatFlowOptionRequest $request, ChatFlowStep $step, ChatFlowOption $option)
    {
        abort_unless($option->step_id === $step->id, 404);

        $validated = $request->validated();

        DB::transaction(function () use ($option, $validated) {
            $nextStepId = null;
            $url = null;
            $replyTextEn = null;
            $replyTextMy = null;
            $replyTextZh = null;

            if ($validated['action'] === ChatFlowOptionAction::GoToStep->value) {
                $nextStepId = $validated['next_step_id'];
            }

            if ($validated['action'] === ChatFlowOptionAction::GoToUrl->value) {
                $url = $validated['url'];
            }

            if ($validated['action'] === ChatFlowOptionAction::ReplyText->value) {
                $replyTextEn = $validated['reply_text_en'] ?? null;
                $replyTextMy = $validated['reply_text_my'] ?? null;
                $replyTextZh = $validated['reply_text_zh'] ?? null;
            }

            $option->update([
                'option_en' => $validated['option_en'],
                'option_my' => $validated['option_my'],
                'option_zh' => $validated['option_zh'],
                'action' => $validated['action'],
                'next_step_id' => $nextStepId,
                'url' => $url,
                'reply_text_en' => $replyTextEn,
                'reply_text_my' => $replyTextMy,
                'reply_text_zh' => $replyTextZh,
                'sort_order' => $validated['sort_order'],
                'is_active' => $validated['is_active'],
            ]);
        });

        return back()->with('success', 'support.chatbot_flows.option_updated');
    }

    /**
     * Delete chatbot option.
     */
    public function destroyOption(ChatFlowStep $step, ChatFlowOption $option)
    {
        abort_unless($option->step_id === $step->id, 404);

        $option->delete();

        return back()->with('success', 'support.chatbot_flows.option_deleted');
    }
}
