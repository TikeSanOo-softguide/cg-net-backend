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
}
