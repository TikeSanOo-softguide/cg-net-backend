<?php

namespace App\Http\Requests\ServiceRequest;

use App\Enums\RequestStatus;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChangePasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('id', $this->user()->id)],
            'broadband_account_number' => [
                'required',
                'string',
                'max:32',
                Rule::exists('users', 'broadband_account_number')->where('id', $this->user()->id),
            ],
            'new_wifi_name' => ['nullable', 'string', 'max:30', 'required_without:new_password'],
            'new_password' => ['required', 'string', 'min:8', 'max:30', 'required_without:new_wifi_name'],
            'contact_name' => ['required', 'string', 'max:30'],
            'contact_phone' => ['required', 'string', 'max:16', 'regex:' . PhoneNumber::SUPPORTED_PATTERN],
            'status' => [
                'required',
                'string',
                'max:16',
                Rule::in([RequestStatus::UnderReview->value, RequestStatus::Cancelled->value]),
            ],
        ];
    }
}
