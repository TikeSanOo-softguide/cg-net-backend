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
            'batch_id' => ['required', 'integer', 'exists:batches,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'agent_id' => ['nullable', 'integer', 'exists:agents,id'],
        ];
    }
}