<?php

namespace App\Http\Requests\Customer;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
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
        /** @var User $customer */
        $customer = $this->route('customer');

        return CustomerData::rules($customer);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('password')) {
            $this->merge([
                'password' => null,
                'password_confirmation' => null,
            ]);
        }
    }
}
