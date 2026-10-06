<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BindAccountNumberRequest extends FormRequest
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
        return [
            'account_number' => [
                'required',
                'string',
                'max:32',
                Rule::unique('users', 'broadband_account_number')->ignore($this->route('customer')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'account_number.required' => 'customers.account_number_required',
            'account_number.max' => 'customers.account_number_max',
            'account_number.unique' => 'customers.account_bound_elsewhere',
        ];
    }
}
