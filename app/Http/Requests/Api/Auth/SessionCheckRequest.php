<?php

namespace App\Http\Requests\Api\Auth;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class SessionCheckRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        try {
            $this->merge(['phone' => PhoneNumber::normalize((string) $this->input('phone'))]);
        } catch (InvalidArgumentException) {
            // The validation rule below returns the normal API validation response.
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['phone' => ['required', 'string', 'regex:'.PhoneNumber::SUPPORTED_PATTERN]];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'This phone number is not supported.'];
    }
}
