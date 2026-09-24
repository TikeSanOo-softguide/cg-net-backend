<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAppVersionRequest extends FormRequest
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
            'platform' => ['required', 'in:ios,android,all'],
            'version' => ['required', 'string', 'max:20'],
            'minimum_version' => ['required', 'string', 'max:20'],
            'download_url' => ['required', 'url', 'max:2048'],

            'release_notes_en' => ['required', 'string'],
            'release_notes_zh' => ['required', 'string'],
            'release_notes_mm' => ['required', 'string'],

            'force_update' => ['required', 'boolean'],
            'status' => ['required', 'in:active,inactive'],
        ];
    }
}
