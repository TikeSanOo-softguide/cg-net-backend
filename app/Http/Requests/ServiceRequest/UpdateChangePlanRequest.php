<?php

namespace App\Http\Requests\ServiceRequest;

use App\Models\ChangePlanRequest;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChangePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'service_type' => ['required', 'string', 'in:FTTH,CG'],
            ...ChangePlanRequest::packageDetailRules('current_package_detail'),
            ...ChangePlanRequest::packageDetailRules('new_package_detail'),
            'preferred_date' => ['required', 'date'],
            'contact_name' => ['required', 'string', 'max:30'],
            'contact_phone' => ['required', 'string', 'max:16', 'regex:' . PhoneNumber::SUPPORTED_PATTERN],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
