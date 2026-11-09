<?php

namespace App\Http\Requests\TopUpCard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOfficeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:50'],
            'name_mm' => ['required', 'string', 'max:50'],
            'name_zh' => ['required', 'string', 'max:50'],
            'cd' => [
                'required',
                'regex:/^\d{2}$/',
                Rule::unique('offices', 'cd')->whereNull('deleted_at')->ignore($this->route('office')),
            ],
            'address_en' => ['required', 'string', 'max:255'],
            'address_mm' => ['required', 'string', 'max:255'],
            'address_zh' => ['required', 'string', 'max:255'],
            'phone_num1' => ['required', 'string', 'max:16'],
            'phone_num2' => ['nullable', 'string', 'max:16'],
            'phone_num3' => ['nullable', 'string', 'max:16'],
            'telegram_link' => ['nullable', 'string', 'max:255'],
            'wechat_link' => ['nullable', 'string', 'max:255'],
            'viber_link' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['cd.unique' => __('top_up_cards.validation.office_cd_unique')];
    }
}
