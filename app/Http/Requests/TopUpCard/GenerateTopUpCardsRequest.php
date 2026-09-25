<?php

namespace App\Http\Requests\TopUpCard;

use App\Support\TopUpCardAgents;
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
            'agent_ids' => ['nullable', 'array', 'max:50'],
            'agent_ids.*' => ['integer', Rule::exists('agents', 'id')->whereNull('deleted_at')],
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
            $agentIds = array_values(array_unique(array_map('intval', $this->input('agent_ids', []))));
            $agentCount = count(TopUpCardAgents::resolveCodes($agentIds));

            if ($agentCount < 1) {
                $validator->errors()->add(
                    'agent_ids',
                    __('top_up_cards.validation.agents_required'),
                );

                return;
            }

            $total = collect($this->input('amounts', []))->sum(fn($tier): int => (int) ($tier['quantity'] ?? 0));
            $issued = $total * $agentCount;

            if ($issued > $maxCards) {
                $validator->errors()->add(
                    'amounts',
                    __('top_up_cards.validation.quantity_total', ['max' => $maxCards]),
                );
            }
        });
    }
}
