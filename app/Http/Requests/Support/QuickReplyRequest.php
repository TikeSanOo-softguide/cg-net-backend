<?php

namespace App\Http\Requests\Support;

use App\Enums\QuickReplyCategory;
use App\Models\QuickReply;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuickReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'keyword' => trim((string) $this->input('keyword', '')),
            'category' => trim((string) $this->input('category', '')),
            'response_en' => trim((string) $this->input('response_en', '')),
            'response_my' => trim((string) $this->input('response_my', '')),
            'response_zh' => trim((string) $this->input('response_zh', '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $quickReply = $this->route('quickReply');
        $ignoreId = $quickReply instanceof QuickReply ? $quickReply->id : null;

        return [
            'keyword' => [
                'required',
                'string',
                'max:50',
                Rule::unique('quick_replies', 'keyword')->whereNull('deleted_at')->ignore($ignoreId),
            ],
            'category' => ['required', 'string', Rule::enum(QuickReplyCategory::class)],
            'response_en' => ['required', 'string', 'max:5000'],
            'response_my' => ['required', 'string', 'max:5000'],
            'response_zh' => ['required', 'string', 'max:5000'],
        ];
    }
}
