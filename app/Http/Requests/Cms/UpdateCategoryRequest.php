<?php

namespace App\Http\Requests\Cms;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('name_en')) {
            $this->merge([
                'slug' => CmsRules::slugFrom((string) $this->string('name_en')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Category $category */
        $category = $this->route('category');

        return [
            ...CmsRules::localized('name', true, 120),
            'slug' => CmsRules::slug('categories', $category->id),
        ];
    }
}
