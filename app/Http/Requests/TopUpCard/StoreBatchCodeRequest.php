<?php

namespace App\Http\Requests\TopUpCard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBatchCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('top-up-cards.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'amount' => [
                'required',
                'integer',
                'min:1',
                'max:1000000',
                Rule::unique('top_up_card_batch_codes', 'amount'),
            ],
            'batch_code' => ['required', 'digits:4', Rule::unique('top_up_card_batch_codes', 'batch_code')],
        ];
    }
}
