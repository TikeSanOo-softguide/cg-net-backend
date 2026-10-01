<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class StorePushNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title_en' => ['required', 'string', 'max:120'],
            'title_zh' => ['required', 'string', 'max:120'],
            'title_my' => ['required', 'string', 'max:120'],
        ];
    }
}
