<?php

namespace App\Http\Requests\Cms;

use App\Enums\NewsStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreNewsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('title')) {
            $this->merge(['slug' => Str::slug((string) $this->string('title')) ?: Str::random(8)]);
        }

        if ($this->has('tag_ids') && ! is_array($this->input('tag_ids'))) {
            $this->merge(['tag_ids' => array_filter(explode(',', (string) $this->input('tag_ids')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:255'],
            'slug' => CmsRules::slug('news', (string) $this->string('lang')),
            'content' => ['required', 'string'],
            'status' => ['required', Rule::enum(NewsStatus::class)],
            'lang' => CmsRules::lang(),
            'image' => CmsRules::image(false),
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', Rule::exists('tags', 'id')->whereNull('deleted_at')],
        ];
    }
}
