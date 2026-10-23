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
            'product_id' => is_int($this->input('product_id'))
                ? (string) $this->input('product_id')
                : $this->input('product_id'),
        ]);
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:93', 'not_regex:/^refund:/i'],
        ];
    }
}
