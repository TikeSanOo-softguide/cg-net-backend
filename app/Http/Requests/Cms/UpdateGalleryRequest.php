<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGalleryRequest extends FormRequest
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
        return [
            'label_en' => ['nullable', 'string', 'max:255'],
            'label_my' => ['nullable', 'string', 'max:255'],
            'label_zh' => ['nullable', 'string', 'max:255'],
            'image' => CmsRules::image(false),
        ];
    }
}
