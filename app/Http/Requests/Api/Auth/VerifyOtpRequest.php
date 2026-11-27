<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'challenge_id' => ['required', 'string', 'size:64'],
            'code' => ['required', 'digits:6'],
            'device_id' => [
                Rule::requiredIf(!config('auth_api.require_password_on_login', true)),
                'nullable',
                'string',
                'max:255',
            ],
            'device_token' => ['nullable', 'string', 'max:512'],
            'platform' => ['nullable', 'string', Rule::in(['android', 'ios', 'web'])],
        ];
    }
}
