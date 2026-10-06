<?php

namespace App\Http\Requests\Api\DeviceToken;

use Illuminate\Foundation\Http\FormRequest;

class DestroyDeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:512'],
        ];
    }
}
