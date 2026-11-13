<?php

namespace App\Http\Requests\ServiceRequest;

use App\Models\RelocationRequest;
use Illuminate\Validation\Rule;
use App\Support\PhoneNumber;
class CreateRelocationRequest extends CreateServiceRequest
{
    protected function requestModel(): string
    {
        return RelocationRequest::class;
    }

    /**
     * Determine if the user is authorized to make this request.
     */
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
     * Get the validation rules that apply to the request.
     *
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
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => __('customers.validation.phone_invalid'),
        ];
    }
}
