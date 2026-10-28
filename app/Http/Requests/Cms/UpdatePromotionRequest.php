<?php

namespace App\Http\Requests\Cms;

use App\Models\Promotion;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePromotionRequest extends FormRequest
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
        /** @var Promotion $promotion */
        $promotion = $this->route('promotion');

        return [
            ...CmsRules::localized('title', true, 255),
            ...CmsRules::localized('description', true, 5000),
            'slug' => CmsRules::slug('promotions', $promotion->id),
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['required', 'boolean'],
            'image' => CmsRules::image(false),
        ];
    }
}
