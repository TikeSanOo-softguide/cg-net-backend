<?php

namespace App\Http\Requests\ServiceRequest;

use App\Enums\FailureType;
use App\Enums\RequestStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFailureReport extends FormRequest
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
            'broadband_account_id' => [
                'required',
                'integer',
                Rule::exists('broadband_accounts', 'id')
                    ->where(function ($query) {
                        $query->where('user_id', $this->user()->id);
                    }),
            ],
            'failure_type' => ['required', Rule::enum(FailureType::class)],
            'description' => ['required', 'string', 'max:5000'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:16'],
            'photos' => ['nullable', 'array', 'size:3'],
            'photos.*' => ['required', 'image', 'max:5120'],
            'status' => ['nullable',  Rule::enum(RequestStatus::class)],
        ];
    }
}
