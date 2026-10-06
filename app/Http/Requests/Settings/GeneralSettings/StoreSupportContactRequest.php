<?php

namespace App\Http\Requests\Settings\GeneralSettings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupportContactRequest extends FormRequest
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
            'phone' => ['required', 'string', 'max:20'],
        ];
    }
}
