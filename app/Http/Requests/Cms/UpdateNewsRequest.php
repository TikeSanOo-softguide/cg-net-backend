<?php

namespace App\Http\Requests\Cms;

use App\Enums\NewsStatus;
use App\Models\News;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
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
        /** @var News $news */
        $news = $this->route('news');

        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            ...CmsRules::localized('title', true, 255),
            'slug' => CmsRules::slug('news', $news->id),
            ...CmsRules::localized('description', true),
            'status' => ['required', Rule::enum(NewsStatus::class)],
            'image' => CmsRules::image(false),
        ];
    }
}
