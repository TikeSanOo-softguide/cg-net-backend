<?php

namespace App\Http\Requests\ServiceRequest;

use App\Models\InstallationApplication;
use App\Support\PhoneNumber;
use Illuminate\Validation\Rule;

class CreateBroadbandApplicationRequest extends CreateServiceRequest
{
    protected function requestModel(): string
    {
        return InstallationApplication::class;
    }

    protected function pendingRequestColumn(): string
    {
        return 'user_id';
    }

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('phone')) {
            return;
        }

        $this->merge([
            'phone' => PhoneNumber::normalizeLeniently($this->input('phone')),
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'area_id' => ['required', 'integer', Rule::exists('areas', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'service_type' => ['required', 'string', 'in:FTTH,CG'],
            'package_detail' => ['required', 'array'],
            'package_detail.product_id' => ['required', 'string', 'max:64'],
            'package_detail.product_name' => ['required', 'string', 'max:255'],
            'package_detail.product_line' => ['nullable', 'string', 'max:255'],
            'package_detail.product_speed' => ['nullable', 'string', 'max:64'],
            'package_detail.product_price' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'package_detail.installation_fee' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'package_detail.month_cycle' => ['required', 'integer', 'min:1'],
            'package_detail.month_cycle_unit' => ['nullable', 'string', 'max:32'],
            'address' => ['required', 'string', 'max:5000'],
            'phone' => ['required', 'string', 'regex:'.PhoneNumber::SUPPORTED_PATTERN],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => __('customers.validation.phone_invalid'),
        ];
    }
}
