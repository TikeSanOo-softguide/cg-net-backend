<?php

namespace App\Http\Requests\ServiceRequest;

use App\Enums\ReviewStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateRelocationRequest extends FormRequest
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
            'broadband_account_id' => ['required', 'integer', Rule::exists('broadband_accounts', 'id')->where('user_id', $this->user_id)],
            'current_address' => ['required', 'string', 'max:5000'],
            'new_address' => ['required', 'string', 'max:5000'],
            'preferred_date' => ['nullable', 'date'],
            'phone' => ['required', 'string', 'max:16'],
            'details' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
