<?php

namespace App\Http\Requests\Settings\GeneralSettings;

use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupportContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (!$this->exists('phone')) {
            return;
        }

        $this->merge([
            'phone' => PhoneNumber::normalizeLeniently($this->input('phone')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:16', 'regex:' . PhoneNumber::SUPPORTED_PATTERN],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => __('customers.validation.phone_invalid'),
        ];
    }
}
