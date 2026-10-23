<?php

namespace App\Http\Requests\Api\Auth;

use App\Support\JsonTranslations;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'verification_token' => ['required', 'string', 'size:64'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'regex:/^\d{6}$/', 'confirmed'],
            'lang' => ['required', 'string', Rule::in(JsonTranslations::LOCALES)],
            'device_token' => ['nullable', 'string', 'max:512'],
            'platform' => ['nullable', 'string', Rule::in(['android', 'ios', 'web'])],
        ];
    }
}
