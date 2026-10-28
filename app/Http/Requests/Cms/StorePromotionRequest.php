<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class StorePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN),
        ]);

        if (! $this->filled('slug') && $this->filled('title_en')) {
            $this->merge([
                'slug' => CmsRules::slugFrom((string) $this->string('title_en')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...CmsRules::localized('title', true, 255),
            ...CmsRules::localized('description', true, 5000),
            'slug' => CmsRules::slug('promotions'),
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['required', 'boolean'],
            'image' => CmsRules::image(true),
        ];
    }
}
