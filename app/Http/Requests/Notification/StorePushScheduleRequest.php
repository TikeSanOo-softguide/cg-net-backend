<?php

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class StorePushScheduleRequest extends FormRequest
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
            'schedule_date' => ['required', 'date_format:Y-m-d'],
            'schedule_time' => ['required', 'date_format:H:i'],
            'scheduled_at' => ['required', 'date', 'after:now'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $date = trim((string) $this->input('schedule_date', ''));
        $time = trim((string) $this->input('schedule_time', ''));

        if ($date !== '' && $time !== '') {
            $this->merge([
                'scheduled_at' => sprintf('%s %s:00', $date, $time),
            ]);
        }
    }
}
