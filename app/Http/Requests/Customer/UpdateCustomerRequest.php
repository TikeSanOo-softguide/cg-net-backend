<?php

namespace App\Http\Requests\Customer;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('customers.update') ?? false;
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return CustomerData::messages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return CustomerData::attributes();
    }

    protected function prepareForValidation(): void
    {
        $payload = [
            'name' => is_string($this->name) ? trim(preg_replace('/\s+/', ' ', $this->name) ?? $this->name) : $this->name,
            'phone' => CustomerData::preparePhone($this->phone),
        ];

        if (! $this->filled('password')) {
            $payload['password'] = null;
            $payload['password_confirmation'] = null;
        }

        $this->merge($payload);
    }
}
