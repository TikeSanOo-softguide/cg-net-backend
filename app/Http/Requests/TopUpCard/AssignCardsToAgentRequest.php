<?php

namespace App\Http\Requests\TopUpCard;

use Illuminate\Foundation\Http\FormRequest;

class AssignCardsToAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'card_ids' => ['required', 'array', 'min:1'],
            'card_ids.*' => ['required', 'integer', 'distinct', 'exists:top_up_card,id'],
            'agent_id' => ['nullable', 'integer', 'exists:agents,id'],
        ];
    }
}