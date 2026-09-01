<?php

namespace App\Http\Requests\TopUpCard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GenerateTopUpCardsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('top-up-cards.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amounts' => ['required', 'array', 'min:1', 'max:12'],
            'amounts.*.value' => ['required', 'numeric', 'min:100', 'max:1000000'],
            'amounts.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'expires_at' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amounts.required' => __('top_up_cards.validation.amounts_required'),
            'amounts.min' => __('top_up_cards.validation.amounts_required'),
            'amounts.*.value.required' => __('top_up_cards.validation.amount_required'),
            'amounts.*.value.min' => __('top_up_cards.validation.amount_min'),
            'amounts.*.quantity.min' => __('top_up_cards.validation.quantity_min'),
            'expires_at.required' => __('top_up_cards.validation.expires_required'),
            'expires_at.after_or_equal' => __('top_up_cards.validation.expires_future'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $total = collect($this->input('amounts', []))->sum(fn ($tier): int => (int) ($tier['quantity'] ?? 0));

            if ($total > 200) {
                $validator->errors()->add('amounts', __('top_up_cards.validation.quantity_total'));
            }
        });
    }
}
