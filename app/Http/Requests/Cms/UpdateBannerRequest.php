<?php

namespace App\Http\Requests\Cms;

use App\Enums\BannerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBannerRequest extends FormRequest
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
            'image_url_en' => CmsRules::image(false),
            'image_url_zh' => CmsRules::image(false),
            'image_url_my' => CmsRules::image(false),
            'type' => ['required', 'string', Rule::enum(BannerType::class)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN),
            'sort_order' => $this->integer('sort_order'),
        ]);
    }
}
