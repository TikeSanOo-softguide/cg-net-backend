<?php

namespace App\Http\Requests\ServiceRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateChangePlanRequest extends FormRequest
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
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('id', $this->user()->id)],
            'broadband_account_id' => ['required', 'integer', Rule::exists('broadband_accounts', 'id')->where('user_id', $this->user()->id)],
            'current_package_id' => ['required', 'integer', Rule::exists('packages', 'id')],
            'new_package_id' => ['required', 'integer', Rule::exists('packages', 'id')->where('is_active', true)],
            'preferred_date' => ['required', 'date'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:16'],
            'note' => ['nullable', 'string'],
        ];
    }
}
