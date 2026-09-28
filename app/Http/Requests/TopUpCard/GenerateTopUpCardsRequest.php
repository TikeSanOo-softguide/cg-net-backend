<?php

namespace App\Http\Requests\TopUpCard;

use App\Support\TopUpCardOffices;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
        $maxCards = (int) config('top_up_cards.max_cards');

        return [
            'amounts' => ['required', 'array', 'min:1', 'max:12'],
            'amounts.*.value' => ['required', 'integer', 'min:50', 'max:1000000'],
            'amounts.*.quantity' => ['required', 'integer', 'min:1', 'max:' . $maxCards],
            'office_ids' => ['nullable', 'array', 'max:50'],
            'office_ids.*' => ['integer', Rule::exists('offices', 'id')->whereNull('deleted_at')],
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
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $maxCards = (int) config('top_up_cards.max_cards');
            $officeIds = array_values(array_unique(array_map('intval', $this->input('office_ids', []))));
            $officeCount = count(TopUpCardOffices::resolveCodes($officeIds));

            if ($officeCount < 1) {
                $validator->errors()->add(
                    'office_ids',
                    __('top_up_cards.validation.offices_required'),
                );

                return;
            }

            $total = collect($this->input('amounts', []))->sum(fn($tier): int => (int) ($tier['quantity'] ?? 0));
            $issued = $total * $officeCount;

            if ($issued > $maxCards) {
                $validator->errors()->add(
                    'amounts',
                    __('top_up_cards.validation.quantity_total', ['max' => $maxCards]),
                );
            }
        });
    }
}
