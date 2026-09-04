<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('customers.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return CustomerData::rules();
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
        $this->merge([
            'name' => is_string($this->name) ? trim(preg_replace('/\s+/', ' ', $this->name) ?? $this->name) : $this->name,
            'phone' => CustomerData::preparePhone($this->phone),
        ]);
    }
}
