<?php

namespace App\Http\Requests\Support\ChatFlows;

use App\Enums\ChatFlowOptionAction;
use App\Models\ChatFlowStep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreChatFlowOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $step = $this->route('step');
        $stepId = $step instanceof ChatFlowStep ? $step->id : $step;
        $action = $this->input('action');

        return [
            'action' => ['required', Rule::enum(ChatFlowOptionAction::class)],
            'option_en' => ['required', 'string', 'max:50'],
            'option_my' => ['required', 'string', 'max:50'],
            'option_zh' => ['required', 'string', 'max:50'],
            'next_step_id' => [
                Rule::requiredIf($action === ChatFlowOptionAction::GoToStep->value),
                'nullable',
                'integer',
                Rule::exists('chat_flow_steps', 'id'),
            ],
            'url' => [
                Rule::requiredIf($action === ChatFlowOptionAction::GoToUrl->value),
                'nullable',
                'url:http,https',
                'max:2048',
            ],
            'reply_text_en' => [
                Rule::requiredIf($action === ChatFlowOptionAction::ReplyText->value),
                'nullable',
                'string',
                'max:5000',
            ],
            'reply_text_my' => [
                Rule::requiredIf($action === ChatFlowOptionAction::ReplyText->value),
                'nullable',
                'string',
                'max:5000',
            ],
            'reply_text_zh' => [
                Rule::requiredIf($action === ChatFlowOptionAction::ReplyText->value),
                'nullable',
                'string',
                'max:5000',
            ],
            'sort_order' => [
                'required',
                'integer',
                'min:0',
                'max:99',
                Rule::unique('chat_flow_options', 'sort_order')->where('step_id', $stepId),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $action = $this->input('action');

            if ($action === ChatFlowOptionAction::GoToStep->value) {
                $step = $this->route('step');

                if ($step instanceof ChatFlowStep && (int) $this->input('next_step_id') === $step->id) {
                    $validator->errors()->add('next_step_id', 'An option cannot link to its own step.');
                }
            }

            if (
                $action === ChatFlowOptionAction::ReplyText->value &&
                !$this->filled('reply_text_en') &&
                !$this->filled('reply_text_my') &&
                !$this->filled('reply_text_zh')
            ) {
                $validator->errors()->add('reply_text_en', 'Please enter at least one reply.');
            }
        });
    }
}
