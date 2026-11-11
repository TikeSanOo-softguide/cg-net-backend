<?php

namespace App\Http\Requests\Api\InternetBill;

use Illuminate\Foundation\Http\FormRequest;

class PayInternetBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'broadband_account_number' => ['required', 'string', 'max:32'],
            'service_type' => ['required', 'string', 'in:FTTH,CG'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:93', 'not_regex:/^refund:/i'],
            'password' => ['required', 'string', 'regex:/^\d{6}$/'],
            'remark' => ['nullable', 'string', 'max:100'],
        ];
    }
}
