<?php

namespace App\Http\Requests\Api\Redeem;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;

class TopUpAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        try {
            $this->merge(['phone' => PhoneNumber::normalize((string) $this->input('phone'))]);
        } catch (InvalidArgumentException) {
            // The validation rule below returns the normal API validation response.
        }

        $pin = $this->input('pin');

        if (is_string($pin)) {
            $this->merge(['pin' => trim($pin)]);
        }

        $header = $this->headers->get('Idempotency-Key');

        $this->merge([
            'idempotency_key' => is_string($header) ? trim($header) : $header,
        ]);
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^[1-9][0-9]{7,14}$/'],
            'pin' => ['required', 'string', 'regex:/^[0-9]+$/', 'max:16'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:93', 'not_regex:/^refund:/i'],
        ];
    }
}
