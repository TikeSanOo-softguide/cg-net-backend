<?php

namespace App\Http\Requests\Reports;

use App\Enums\LedgerHealthScanType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RunLedgerHealthCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = $this->string('type')->toString();

        return [
            'type' => ['required', Rule::in([LedgerHealthScanType::Full->value, LedgerHealthScanType::Manual->value])],
            'from' => [
                Rule::requiredIf($type === LedgerHealthScanType::Manual->value),
                'nullable',
                'date',
            ],
            'to' => [
                Rule::requiredIf($type === LedgerHealthScanType::Manual->value),
                'nullable',
                'date',
                'after:from',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->string('type')->toString() !== LedgerHealthScanType::Manual->value) {
                return;
            }

            $from = $this->date('from');
            $to = $this->date('to');

            if ($from === null || $to === null) {
                return;
            }

            if ($from->diffInDays($to) > 31) {
                $validator->errors()->add('to', __('ledger_health_report.validation.range_too_long'));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => __('ledger_health_report.validation.type_required'),
            'type.in' => __('ledger_health_report.validation.type_invalid'),
            'from.required' => __('ledger_health_report.validation.from_required'),
            'to.required' => __('ledger_health_report.validation.to_required'),
            'to.after' => __('ledger_health_report.validation.to_after_from'),
        ];
    }
}
