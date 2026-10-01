<?php

namespace App\Http\Requests\Api\Redeem;

use Illuminate\Foundation\Http\FormRequest;

class SerialNoCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'serial_no' => ['required', 'string', 'max:32'],
        ];
    }
}