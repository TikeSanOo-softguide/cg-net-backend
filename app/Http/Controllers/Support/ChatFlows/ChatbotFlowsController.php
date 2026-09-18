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
     * Display the single chatbot flow.
     */
    public function index(Request $request): Response
    {
        $steps = ChatFlowStep::query()
            ->with([
                'translations',
                'options.translations',
                'options.replies',
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

            'translations.en.message' => [
                'required',
                'string',
                'max:1000',
            ],

            'translations.my.message' => [
                'required',
                'string',
                'max:1000',
            ],

            'translations.zh.message' => [
                'required',
                'string',
                'max:1000',
            ],
        ]);

        $step = DB::transaction(function () use ($validated) {

            $hasStartStep = ChatFlowStep::query()
                ->where('is_start', true)
                ->where('is_active', true)
                ->exists();

            /*
             * First step automatically becomes START.
             */
            $isStart = !$hasStartStep || ($validated['is_start'] ?? false);

            /*
             * There can only be one START step.
             */
            if ($isStart) {
                ChatFlowStep::query()
                    ->update([
                        'is_start' => false,
                    ]);
            }

            $sortOrder = (
                ChatFlowStep::query()
                ->max('sort_order') ?? 0
            ) + 1;

            $step = ChatFlowStep::create([
                'name' => $validated['name'],
                'is_start' => $isStart,
                'sort_order' => $sortOrder,
                'is_active' => true,
            ]);

            /*
             * Create translations.
             */
            $step->translations()->createMany([
                [
                    'language' => 'en',
                    'message' => $validated['translations']['en']['message'],
                ],
                [
                    'language' => 'my',
                    'message' => $validated['translations']['my']['message'],
                ],
                [
                    'language' => 'zh',
                    'message' => $validated['translations']['zh']['message'],
                ],
            ]);

            return $step;
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

            'translations.en.message' => [
                'required',
                'string',
                'max:1000',
            ],

            'translations.my.message' => [
                'required',
                'string',
                'max:1000',
            ],

            'translations.zh.message' => [
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
                'is_start' => $isStart,
            ]);

            foreach (['en', 'my', 'zh'] as $language) {
                $step->translations()->updateOrCreate(
                    [
                        'language' => $language,
                    ],
                    [
                        'message' =>
                        $validated['translations'][$language]['message'],
                    ]
                );
            }
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
         * Do not allow deleting START step.
         *
         * This avoids having a chatbot with no entry point.
         */
        if ($step->is_start) {
            return back()->withErrors([
                'step' =>
                'The START step cannot be deleted. Set another step as START first.',
            ]);
        }

        /*
         * Check whether another option points to this step.
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

            $option = $step->options()->create([
                'action' => $validated['action'],

                'next_step_id' =>
                $validated['next_step_id'] ?? null,

                'url' =>
                $validated['url'] ?? null,

                'sort_order' => $sortOrder,

                'is_active' => true,
            ]);

            /*
             * Option labels.
             */
            foreach (['en', 'my', 'zh'] as $language) {
                $option->translations()->create([
                    'language' => $language,

                    'label' =>
                    $validated['translations'][$language]['label'],
                ]);
            }

            /*
             * Reply text.
             */
            if ($validated['action'] === 'reply_text') {

                foreach (['en', 'my', 'zh'] as $language) {

                    $reply =
                        $validated['replies'][$language]['reply_text']
                        ?? null;

                    if (!empty($reply)) {
                        $option->replies()->create([
                            'language' => $language,
                            'reply_text' => $reply,
                        ]);
                    }
                }
            }
        });

        return back()->with(
            'success',
            'Option added successfully.'
        );
    }

    public function updateOption(
        Request $request,
        ChatFlowStep $step,
        ChatFlowOption $option
    ) {
        /*
         * Make sure option belongs to this step.
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

            $option->update([
                'action' =>
                $validated['action'],

                'next_step_id' =>
                $validated['next_step_id'] ?? null,

                'url' =>
                $validated['url'] ?? null,
            ]);

            /*
             * Update labels.
             */
            foreach (['en', 'my', 'zh'] as $language) {

                $option->translations()->updateOrCreate(
                    [
                        'language' => $language,
                    ],
                    [
                        'label' =>
                        $validated['translations'][$language]['label'],
                    ]
                );
            }

            /*
             * Remove old replies.
             *
             * This is important when changing:
             *
             * Reply Text
             *      ↓
             * Go to Step
             *
             * Old reply text should not remain.
             */
            $option->replies()->delete();

            /*
             * Create new replies if needed.
             */
            if ($validated['action'] === 'reply_text') {

                foreach (['en', 'my', 'zh'] as $language) {

                    $reply =
                        $validated['replies'][$language]['reply_text']
                        ?? null;

                    if (!empty($reply)) {

                        $option->replies()->create([
                            'language' => $language,
                            'reply_text' => $reply,
                        ]);
                    }
                }
            }
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
     * The new step and option are created
     * in one transaction.
     */
    public function createStepFromOption(
        Request $request,
        ChatFlowStep $step
    ) {
        $validated = $request->validate([
            'translations.en.label' => [
                'required',
                'string',
                'max:255',
            ],

            'translations.my.label' => [
                'required',
                'string',
                'max:255',
            ],

            'new_step.name' => [
                'required',
                'string',
                'max:255',
            ],

            'new_step.translations.en.message' => [
                'required',
                'string',
                'max:1000',
            ],

            'new_step.translations.my.message' => [
                'required',
                'string',
                'max:1000',
            ],

            'new_step.translations.zh.message' => [
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
                ChatFlowStep::query()
                ->max('sort_order') ?? 0
            ) + 1;

            $newStep = ChatFlowStep::create([
                'name' =>
                $validated['new_step']['name'],

                'is_start' => false,

                'sort_order' => $sortOrder,

                'is_active' => true,
            ]);

            /*
             * Step translations.
             */
            $newStep->translations()->createMany([
                [
                    'language' => 'en',
                    'message' =>
                    $validated['new_step']['translations']['en']['message'],
                ],
                [
                    'language' => 'my',
                    'message' =>
                    $validated['new_step']['translations']['my']['message'],
                ],
                [
                    'language' => 'zh',
                    'message' =>
                    $validated['new_step']['translations']['zh']['message'],
                ],
            ]);

            /*
             * Create option and automatically
             * connect it to the new step.
             */
            $optionSortOrder = (
                $step->options()->max('sort_order') ?? 0
            ) + 1;

            $option = $step->options()->create([
                'action' => 'go_to_step',

                'next_step_id' =>
                $newStep->id,

                'sort_order' =>
                $optionSortOrder,

                'is_active' => true,
            ]);

            /*
             * Option labels.
             */
            $option->translations()->createMany([
                [
                    'language' => 'en',
                    'label' =>
                    $validated['translations']['en']['label'],
                ],
                [
                    'language' => 'my',
                    'label' =>
                    $validated['translations']['my']['label'],
                ],
                [
                    'language' => 'zh',
                    'label' =>
                    $validated['translations']['zh']['label'],
                ],
            ]);
        });

        return back()->with(
            'success',
            'Step created and connected successfully.'
        );
    }

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

            /*
             * English label.
             */
            'translations.en.label' => [
                'required',
                'string',
                'max:255',
            ],

            /*
             * Burmese label.
             */
            'translations.my.label' => [
                'required',
                'string',
                'max:255',
            ],

            'translations.zh.label' => [
                'required',
                'string',
                'max:255',
            ],

            /*
             * Step destination.
             */
            'next_step_id' => [
                'nullable',
                'integer',
                'exists:chat_flow_steps,id',
            ],

            /*
             * URL destination.
             */
            'url' => [
                'nullable',
                'url:http,https',
                'max:2048',
            ],

            /*
             * Reply text.
             */
            'replies.en.reply_text' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'replies.my.reply_text' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'replies.zh.reply_text' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);
    }

    /**
     * Validate fields according to selected action.
     */
    private function validateOptionAction(
        array $validated,
        ChatFlowStep $step,
        ?ChatFlowOption $option = null
    ): void {
        $action = $validated['action'];

        if ($action === 'go_to_step') {
            if (empty($validated['next_step_id'])) {
                abort(
                    422,
                    'Please select a next step.'
                );
            }

            /*
             * Make sure target step exists.
             */
            $targetStep = ChatFlowStep::query()
                ->whereKey(
                    $validated['next_step_id']
                )
                ->where('is_active', true)
                ->first();

            abort_unless(
                $targetStep,
                422
            );

            if (
                $option &&
                $targetStep->id === $option->step_id
            ) {
                abort(
                    422,
                    'An option cannot link to its own step.'
                );
            }
            return;
        }

        if ($action === 'go_to_url') {
            if (empty($validated['url'])) {
                abort(
                    422,
                    'Please enter a URL.'
                );
            }
            return;
        }

        if ($action === 'reply_text') {

            $hasEnglishReply =
                !empty($validated['replies']['en']['reply_text']
                    ?? null);

            $hasMyanmarReply =
                !empty($validated['replies']['my']['reply_text']
                    ?? null);

            $hasChineseReply =
                !empty($validated['replies']['zh']['reply_text']
                    ?? null);

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

            if (!empty($validated['next_step_id'])) {

                $exists = ChatFlowStep::query()
                    ->whereKey(
                        $validated['next_step_id']
                    )
                    ->where('is_active', true)
                    ->exists();

                abort_unless(
                    $exists,
                    422
                );
            }

            return;
        }

        if ($action === 'transfer_agent') {
            return;
        }


        if ($action === 'close_chat') {
            return;
        }
    }
}
