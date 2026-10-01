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
        $phone = $this->input('phone');

        if (is_string($phone)) {
            try {
                $this->merge(['phone' => PhoneNumber::normalize($phone)]);
            } catch (InvalidArgumentException) {
                $this->merge(['phone' => trim($phone)]);
            }
        }

        $pin = $this->input('pin');

        if (is_string($pin)) {
            $this->merge(['pin' => trim($pin)]);
        }

        $idempotencyKey = $this->input('idempotency_key');

        if (is_string($idempotencyKey)) {
            $this->merge(['idempotency_key' => trim($idempotencyKey)]);
        }
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^[1-9][0-9]{7,14}$/'],
            'pin' => ['required', 'string', 'max:128'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:100'],
        ];
    }
}