<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('name_en')) {
            $this->merge(['slug' => Str::slug((string) $this->string('name_en')) ?: Str::random(8)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:100'],
            'name_zh' => ['required', 'string', 'max:100'],
            'name_my' => ['required', 'string', 'max:100'],
            'slug' => CmsRules::slug('categories'),
        ];
    }
}
