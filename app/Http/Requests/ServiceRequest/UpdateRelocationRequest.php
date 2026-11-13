<?php

namespace App\Http\Requests\ServiceRequest;

use App\Enums\RequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Support\PhoneNumber;
class UpdateRelocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'broadband_account_number' => [
                'required',
                'string',
                'max:32',
                Rule::exists('users', 'broadband_account_number')->where('id', $this->user()->id),
            ],
            'contact_name' => ['required', 'string', 'max:30'],
            'current_address' => ['required', 'string', 'max:255'],
            'new_address' => ['required', 'string', 'max:255'],
            'preferred_date' => ['nullable', 'date'],
            'phone' => ['required', 'string', 'max:16', 'regex:' . PhoneNumber::SUPPORTED_PATTERN],
            'details' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in([RequestStatus::UnderReview->value, RequestStatus::Cancelled->value])],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => __('customers.validation.phone_invalid'),
        ];
    }
}
