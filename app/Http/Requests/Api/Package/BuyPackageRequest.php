<?php

namespace App\Http\Requests\Api\Package;

use Illuminate\Foundation\Http\FormRequest;

class BuyPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $header = $this->headers->get('Idempotency-Key');

        $this->merge([
            'idempotency_key' => is_string($header) ? trim($header) : $header,
        ]);
    }

    public function rules(): array
    {
        return [
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:93', 'not_regex:/^refund:/i'],
        ];
    }
}
