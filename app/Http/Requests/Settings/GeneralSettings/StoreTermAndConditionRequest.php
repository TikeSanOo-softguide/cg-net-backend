<?php

namespace App\Http\Requests\Settings\GeneralSettings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTermAndConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.create') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title_en' => ['required', 'string', 'max:120'],
            'title_zh' => ['required', 'string', 'max:120'],
            'title_my' => ['required', 'string', 'max:120'],

            'description_en' => ['required', 'string'],
            'description_zh' => ['required', 'string'],
            'description_my' => ['required', 'string'],
        ];
    }
}
