<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdatePromotionRequest extends FormRequest
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
            'title_en' => ['required', 'string', 'max:255'],
            'title_my' => ['required', 'string', 'max:255'],
            'title_zh' => ['required', 'string', 'max:255'],
            'description_en' => ['required', 'string', 'max:5000'],
            'description_my' => ['required', 'string', 'max:5000'],
            'description_zh' => ['required', 'string', 'max:5000'],
            'slug' => CmsRules::slug('promotions', $this->promotion?->id),
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['required', 'boolean'],
            'image' => CmsRules::image(false),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN),
        ]);
        if (! $this->filled('slug') && $this->filled('title_en')) {
            $this->merge(['slug' => Str::slug((string) $this->string('title_en')) ?: Str::random(8)]);
        }
    }
}
