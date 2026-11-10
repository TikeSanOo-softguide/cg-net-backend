<?php

namespace App\Http\Requests\ServiceRequest;

use App\Enums\FailureType;
use App\Enums\RequestStatus;
use App\Models\FailureReport;
use App\Support\PhoneNumber;
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

    protected function prepareForValidation(): void
    {
        $this->mergeStoredBroadbandAccount();
    }

    private function mergeStoredBroadbandAccount(): void
    {
        $stored = $this->user()?->broadband_account_number;
        if (!is_string($stored) || $stored === '') {
            return;
        }

        $given = trim((string) $this->input('broadband_account_number', ''));
        if ($given === '' || $given === $stored) {
            return;
        }

        foreach (['FTTH', 'CG'] as $prefix) {
            if (str_starts_with($stored, $prefix) && substr($stored, strlen($prefix)) === $given) {
                $this->merge(['broadband_account_number' => $stored]);

                return;
            }
        }
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
            'description' => ['required', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:30'],
            'contact_phone' => ['required', 'string', 'max:16', 'regex:' . PhoneNumber::SUPPORTED_PATTERN],
            'status' => [
                'nullable',
                'string',
                'max:16',
                Rule::in([RequestStatus::UnderReview->value, RequestStatus::Cancelled->value]),
            ],
            'photos' => ['nullable', 'array', 'min:1'],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
