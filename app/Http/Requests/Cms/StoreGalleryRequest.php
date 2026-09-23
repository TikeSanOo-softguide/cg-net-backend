<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class StoreGalleryRequest extends FormRequest
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
            'label_en' => ['nullable', 'string', 'max:120'],
            'label_my' => ['nullable', 'string', 'max:120'],
            'label_zh' => ['nullable', 'string', 'max:120'],
            'image' => CmsRules::image(true),
        ];
    }
}
