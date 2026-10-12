<?php

namespace App\Http\Requests\ServiceRequest;

use App\Models\RelocationRequest;
use Illuminate\Validation\Rule;

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
            'current_address' => ['required', 'string', 'max:5000'],
            'new_address' => ['required', 'string', 'max:5000'],
            'preferred_date' => ['nullable', 'date'],
            'phone' => ['required', 'string', 'max:16'],
            'details' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
