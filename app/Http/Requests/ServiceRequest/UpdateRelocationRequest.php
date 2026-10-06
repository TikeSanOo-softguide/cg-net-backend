<?php

namespace App\Http\Requests\ServiceRequest;

use App\Enums\RequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRelocationRequest extends FormRequest
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
            'current_address' => ['required', 'string', 'max:5000'],
            'new_address' => ['required', 'string', 'max:5000'],
            'preferred_date' => ['required', 'date'],
            'phone' => ['required', 'string', 'max:16'],
            'details' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::enum(RequestStatus::class)],
        ];
    }
}
