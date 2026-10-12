<?php

namespace App\Http\Requests\ServiceRequest;

use App\Models\ChangePasswordRequest;
use Illuminate\Validation\Rule;

class CreateChangePasswordRequest extends CreateServiceRequest
{
    protected function requestModel(): string
    {
        return ChangePasswordRequest::class;
    }

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
            'new_wifi_name' => ['nullable', 'string', 'required_without:new_password'],
            'new_password' => ['nullable', 'string', 'required_without:new_wifi_name'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:255'],
        ];
    }
}
