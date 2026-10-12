<?php

namespace App\Http\Requests\ServiceRequest;

use App\Enums\FailureType;
use App\Models\FailureReport;
use Illuminate\Validation\Rule;

class CreateFailureReport extends CreateServiceRequest
{
    protected function requestModel(): string
    {
        return FailureReport::class;
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
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
            'failure_type' => ['required', Rule::enum(FailureType::class)],
            'description' => ['required', 'string', 'max:5000'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:16'],
            'status' => ['nullable', 'string', 'max:255'],
            'photos' => ['required', 'array', 'size:3'],
            'photos.*' => ['required', 'image', 'max:5120'],
        ];
    }
}
