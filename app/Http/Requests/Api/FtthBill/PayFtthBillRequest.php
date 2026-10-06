<?php

namespace App\Http\Requests\Api\FtthBill;

use Illuminate\Foundation\Http\FormRequest;

class PayFtthBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'broadband_account_number' => ['required', 'string', 'max:32'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:100'],
        ];
    }
}
