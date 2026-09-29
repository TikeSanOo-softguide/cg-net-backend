<?php

namespace App\Http\Requests\Support\ChatFlows;

use Illuminate\Foundation\Http\FormRequest;

class StoreChatFlowStepRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:50'],
            'is_start' => ['boolean'],
            'message_en' => ['required', 'string', 'max:5000'],
            'message_my' => ['required', 'string', 'max:5000'],
            'message_zh' => ['required', 'string', 'max:5000'],
        ];
    }
}
