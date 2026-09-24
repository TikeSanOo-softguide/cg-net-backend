<?php

namespace App\Http\Requests\TopUpCard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'cd' => ['required', 'integer', 'digits:2', Rule::unique('agents', 'cd')->whereNull('deleted_at')],
            'address' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'cd.unique' => __('top_up_cards.validation.agent_cd_unique'),
        ];
    }
}