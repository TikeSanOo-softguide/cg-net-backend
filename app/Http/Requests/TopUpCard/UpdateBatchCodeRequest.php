<?php

namespace App\Http\Requests\TopUpCard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBatchCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('top-up-cards.update') ?? false;
    }

    public function rules(): array
    {
        $batchCode = $this->route('batchCode');

        return [
            'amount' => [
                'required',
                'integer',
                'min:1',
                'max:1000000',
                Rule::unique('top_up_card_batch_codes', 'amount')->ignore($batchCode),
            ],
            'batch_code' => [
                'required',
                'digits:4',
                Rule::unique('top_up_card_batch_codes', 'batch_code')->ignore($batchCode),
            ],
        ];
    }
}
