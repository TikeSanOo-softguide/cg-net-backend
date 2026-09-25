<?php

namespace App\Http\Controllers\Support\ChatFlows;

use App\Http\Controllers\Controller;
use App\Models\ChatFlowOption;
use App\Models\ChatFlowStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
            ->with([
                'options.nextStep',
            ])
            ->orderBy('sort_order')
            ->get();

        return Inertia::render(
            'Support/chatFlows/Index',
            [
                'steps' => $steps,
            ]
        );
    }

    /**
     * Create a new chatbot step.
     */
    public function storeStep(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'is_start' => [
                'boolean',
            ],

            'message_en' => [
                'required',
                'string',
                'max:1000',
            ],

            'message_my' => [
                'required',
                'string',
                'max:1000',
            ],

            'message_zh' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        DB::transaction(function () use ($validated) {
            $hasStartStep = ChatFlowStep::query()
                ->where('is_start', true)
                ->where('is_active', true)
                ->exists();

            /*
             * The first step automatically becomes START.
             */
            $isStart = !$hasStartStep || ($validated['is_start'] ?? false);

            /*
             * There can only be one START step.
             */
            if ($isStart) {
                ChatFlowStep::query()
                    ->where('is_start', true)
                    ->update([
                        'is_start' => false,
                    ]);
            }

            $sortOrder = (
                ChatFlowStep::query()->max('sort_order') ?? 0
            ) + 1;

            ChatFlowStep::create([
                'name' => $validated['name'],
                'message_en' => $validated['message_en'],
                'message_my' => $validated['message_my'],
                'message_zh' => $validated['message_zh'],
                'is_start' => $isStart,
                'sort_order' => $sortOrder,
                'is_active' => true,
            ]);
        });

        return back()->with(
            'success',
            'Step created successfully.'
        );
    }

    /**
     * Update a chatbot step.
     */
    public function updateStep(
        Request $request,
        ChatFlowStep $step
    ) {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'is_start' => [
                'boolean',
            ],

            'message_en' => [
                'required',
                'string',
                'max:1000',
            ],

            'message_my' => [
                'required',
                'string',
                'max:1000',
            ],

            'message_zh' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        DB::transaction(function () use (
            $step,
            $validated
        ) {
            $isStart = $validated['is_start'] ?? false;

            /*
             * If this step becomes START,
             * remove START from all other steps.
             */
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

        return back()->with(
            'success',
            'Step updated successfully.'
        );
    }

    /**
     * Delete a chatbot step.
     */
    public function destroyStep(
        ChatFlowStep $step
    ) {
        /*
         * START step cannot be deleted.
         */
        if ($step->is_start) {
            return back()->withErrors([
                'step' =>
                'The START step cannot be deleted. Set another step as START first.',
            ]);
        }

        /*
         * Do not delete a step that is used
         * as the destination of another option.
         */
        $isUsed = ChatFlowOption::query()
            ->where('next_step_id', $step->id)
            ->exists();

        if ($isUsed) {
            return back()->withErrors([
                'step' =>
                'This step is currently used by another option. Remove those connections first.',
            ]);
        }

        $step->delete();

        return back()->with(
            'success',
            'Step deleted successfully.'
        );
    }

    /**
     * Create a chatbot option.
     */
    public function storeOption(
        Request $request,
        ChatFlowStep $step
    ) {
        $validated = $this->validateOption($request);

        $this->validateOptionAction(
            $validated,
            $step
        );

        DB::transaction(function () use (
            $step,
            $validated
        ) {
            $sortOrder = (
                $step->options()->max('sort_order') ?? 0
            ) + 1;

            $step->options()->create([
                'option_en' => $validated['option_en'],
                'option_my' => $validated['option_my'],
                'option_zh' => $validated['option_zh'],

                'action' => $validated['action'],

                'next_step_id' =>
                $validated['next_step_id'] ?? null,

                'url' =>
                $validated['url'] ?? null,

                'reply_text_en' =>
                $validated['reply_text_en'] ?? null,

                'reply_text_my' =>
                $validated['reply_text_my'] ?? null,

                'reply_text_zh' =>
                $validated['reply_text_zh'] ?? null,

                'sort_order' => $sortOrder,

                'is_active' => true,
            ]);
        });

        return back()->with(
            'success',
            'Option added successfully.'
        );
    }

    /**
     * Update a chatbot option.
     */
    public function updateOption(
        Request $request,
        ChatFlowStep $step,
        ChatFlowOption $option
    ) {
        /*
         * Make sure the option belongs to this step.
         */
        abort_unless(
            $option->step_id === $step->id,
            404
        );

        $validated = $this->validateOption($request);

        $this->validateOptionAction(
            $validated,
            $step,
            $option
        );

        DB::transaction(function () use (
            $option,
            $validated
        ) {
            /*
             * Clear fields that are not used by the
             * selected action.
             *
             * This prevents old URL/reply/step data
             * from remaining after changing action.
             */
            $nextStepId = null;
            $url = null;

            $replyTextEn = null;
            $replyTextMy = null;
            $replyTextZh = null;

            if ($validated['action'] === 'go_to_step') {
                $nextStepId = $validated['next_step_id'];
            }

            if ($validated['action'] === 'go_to_url') {
                $url = $validated['url'];
            }

            if ($validated['action'] === 'reply_text') {
                $replyTextEn =
                    $validated['reply_text_en'] ?? null;

                $replyTextMy =
                    $validated['reply_text_my'] ?? null;

                $replyTextZh =
                    $validated['reply_text_zh'] ?? null;
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
            ]);
        });

        return back()->with(
            'success',
            'Option updated successfully.'
        );
    }

    /**
     * Delete chatbot option.
     */
    public function destroyOption(
        ChatFlowStep $step,
        ChatFlowOption $option
    ) {
        abort_unless(
            $option->step_id === $step->id,
            404
        );

        $option->delete();

        return back()->with(
            'success',
            'Option deleted successfully.'
        );
    }

    /**
     * Create a new step and connect it to
     * the current step with a new option.
     */
    public function createStepFromOption(
        Request $request,
        ChatFlowStep $step
    ) {
        $validated = $request->validate([
            'option_en' => [
                'required',
                'string',
                'max:50',
            ],

            'option_my' => [
                'required',
                'string',
                'max:50',
            ],

            'option_zh' => [
                'required',
                'string',
                'max:50',
            ],

            'new_step.name' => [
                'required',
                'string',
                'max:255',
            ],

            'new_step.message_en' => [
                'required',
                'string',
                'max:1000',
            ],

            'new_step.message_my' => [
                'required',
                'string',
                'max:1000',
            ],

            'new_step.message_zh' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        DB::transaction(function () use (
            $step,
            $validated
        ) {
            /*
             * Create new step.
             */
            $sortOrder = (
                ChatFlowStep::query()->max('sort_order') ?? 0
            ) + 1;

            $newStep = ChatFlowStep::create([
                'name' =>
                $validated['new_step']['name'],

                'message_en' =>
                $validated['new_step']['message_en'],

                'message_my' =>
                $validated['new_step']['message_my'],

                'message_zh' =>
                $validated['new_step']['message_zh'],

                'is_start' => false,

                'sort_order' => $sortOrder,

                'is_active' => true,
            ]);

            /*
             * Create option and automatically
             * connect it to the new step.
             */
            $optionSortOrder = (
                $step->options()->max('sort_order') ?? 0
            ) + 1;

            $step->options()->create([
                'option_en' =>
                $validated['option_en'],

                'option_my' =>
                $validated['option_my'],

                'option_zh' =>
                $validated['option_zh'],

                'action' => 'go_to_step',

                'next_step_id' =>
                $newStep->id,

                'url' => null,

                'reply_text_en' => null,
                'reply_text_my' => null,
                'reply_text_zh' => null,

                'sort_order' =>
                $optionSortOrder,

                'is_active' => true,
            ]);
        });

        return back()->with(
            'success',
            'Step created and connected successfully.'
        );
    }

    /**
     * Validate common option fields.
     */
    private function validateOption(
        Request $request
    ): array {
        return $request->validate([
            'action' => [
                'required',
                Rule::in([
                    'go_to_step',
                    'go_to_url',
                    'reply_text',
                    'transfer_agent',
                    'main_menu',
                    'close_chat',
                ]),
            ],

            'option_en' => [
                'required',
                'string',
                'max:50',
            ],

            'option_my' => [
                'required',
                'string',
                'max:50',
            ],

            'option_zh' => [
                'required',
                'string',
                'max:50',
            ],

            'next_step_id' => [
                'nullable',
                'integer',
                'exists:chat_flow_steps,id',
            ],

            'url' => [
                'nullable',
                'url:http,https',
                'max:2048',
            ],

            'reply_text_en' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'reply_text_my' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'reply_text_zh' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);
    }

    /**
     * Validate fields according to the selected action.
     */
    private function validateOptionAction(
        array $validated,
        ChatFlowStep $step,
        ?ChatFlowOption $option = null
    ): void {
        $action = $validated['action'];

        /*
         * Go to Step
         */
        if ($action === 'go_to_step') {
            if (empty($validated['next_step_id'])) {
                abort(
                    422,
                    'Please select a next step.'
                );
            }

            $targetStep = ChatFlowStep::query()
                ->whereKey($validated['next_step_id'])
                ->where('is_active', true)
                ->first();

            abort_unless(
                $targetStep,
                422
            );

            /*
             * Prevent direct self-loop.
             */
            if (
                $targetStep->id === $step->id
            ) {
                abort(
                    422,
                    'An option cannot link to its own step.'
                );
            }

            return;
        }

        /*
         * Go to URL
         */
        if ($action === 'go_to_url') {
            if (empty($validated['url'])) {
                abort(
                    422,
                    'Please enter a URL.'
                );
            }

            return;
        }

        /*
         * Reply Text
         */
        if ($action === 'reply_text') {
            $hasEnglishReply =
                !empty($validated['reply_text_en'] ?? null);

            $hasMyanmarReply =
                !empty($validated['reply_text_my'] ?? null);

            $hasChineseReply =
                !empty($validated['reply_text_zh'] ?? null);

            if (
                !$hasEnglishReply &&
                !$hasMyanmarReply &&
                !$hasChineseReply
            ) {
                abort(
                    422,
                    'Please enter at least one reply.'
                );
            }

            return;
        }

        /*
         * Transfer to Agent
         */
        if ($action === 'transfer_agent') {
            return;
        }

        /*
         * Main Menu
         */
        if ($action === 'main_menu') {
            return;
        }

        /*
         * Close Chat
         */
        if ($action === 'close_chat') {
            return;
        }
    }
}
