<?php

namespace App\Http\Requests\Notification;

use App\Enums\AnnouncementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(AnnouncementType::class)],
            'title_en' => ['required', 'string', 'max:120'],
            'title_zh' => ['required', 'string', 'max:120'],
            'title_my' => ['required', 'string', 'max:120'],
            'content_en' => ['required', 'string', 'max:5000'],
            'content_zh' => ['required', 'string', 'max:5000'],
            'content_my' => ['required', 'string', 'max:5000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
