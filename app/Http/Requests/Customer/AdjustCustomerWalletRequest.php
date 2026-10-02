<?php

namespace App\Http\Requests\Customer;

use App\Models\LedgerTransaction;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AdjustCustomerWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->can('customers.update') || $user->can('billing.update'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'integer', 'min:1'],
            'note' => ['required', 'string', 'max:500'],
            'related_transaction_id' => ['nullable', 'integer', 'exists:ledger_transactions,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var User $customer */
            $customer = $this->route('customer');
            $relatedId = $this->integer('related_transaction_id');

            if ($relatedId < 1) {
                return;
            }

            $related = LedgerTransaction::query()->with('wallet:id,user_id')->find($relatedId);

            if ($related === null) {
                return;
            }

            if ((int) $related->wallet?->user_id !== (int) $customer->id) {
                $validator->errors()->add('related_transaction_id', __('customers.wallet_adjust.related_mismatch'));
            }
        });
    }
}
