<?php

namespace App\Http\Requests\Cms;

use App\Enums\NewsStatus;
use App\Models\News;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
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
            $this->merge(['slug' => Str::slug((string) $this->string('title_en')) ?: Str::random(8)]);
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
            'title_en' => ['required', 'string', 'max:255'],
            'title_zh' => ['required', 'string', 'max:255'],
            'title_my' => ['required', 'string', 'max:255'],
            'description_en' => ['required', 'string'],
            'description_zh' => ['required', 'string'],
            'description_my' => ['required', 'string'],
            'slug' => CmsRules::slug('news', $news->id),
            'status' => ['required', Rule::enum(NewsStatus::class)],
            'image' => CmsRules::image(false),
        ];
    }
}
