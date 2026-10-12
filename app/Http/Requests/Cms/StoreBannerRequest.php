<?php

namespace App\Http\Requests\Cms;

use App\Enums\BannerType;
use App\Models\Banner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBannerRequest extends FormRequest
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
            'image_url_en' => CmsRules::image(true),
            'image_url_zh' => CmsRules::image(true),
            'image_url_my' => CmsRules::image(true),
            'type' => [
                'required',
                'string',
                Rule::enum(BannerType::class),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $limit = match ($value) {
                        BannerType::AppEntry->value => 1,
                        BannerType::AppPopUp->value => 2,
                        default => null,
                    };

                    if ($limit !== null && Banner::query()->where('type', $value)->count() >= $limit) {
                        $fail(__('Maximum limit reached (:limit/:limit).', ['limit' => $limit]));
                    }
                },
            ],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999999999'],
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
