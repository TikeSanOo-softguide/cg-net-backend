<?php

namespace App\Http\Requests\ServiceRequest;

use App\Enums\ChangePasswordStatus;
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
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'broadband_account_id' => [
                'required',
                'integer',
                Rule::exists('broadband_accounts', 'id')->where('user_id', $this->user_id),
            ],
            'new_wifi_name' => ['nullable', 'string', 'required_without:new_password'],
            'new_password' => ['nullable', 'string', 'required_without:new_wifi_name'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:16', Rule::enum(ChangePasswordStatus::class)],
        ];
    }
}
