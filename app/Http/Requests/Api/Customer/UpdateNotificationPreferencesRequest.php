<?php

namespace App\Http\Requests\Api\Customer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $aliases = [
            'announcements_notifications' => 'announcements_enabled',
            'system_notifications' => 'system_enabled',
            'promotion_notifications' => 'promotion_enabled',
            'general_notifications' => 'general_enabled',
        ];

        foreach ($aliases as $alias => $field) {
            if (!$this->exists($field) && $this->exists($alias)) {
                $this->merge([$field => $this->input($alias)]);
            }
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'announcements_enabled' => [
                'required_without_all:system_enabled,promotion_enabled,general_enabled',
                'boolean',
            ],
            'system_enabled' => [
                'required_without_all:announcements_enabled,promotion_enabled,general_enabled',
                'boolean',
            ],
            'promotion_enabled' => [
                'required_without_all:announcements_enabled,system_enabled,general_enabled',
                'boolean',
            ],
            'general_enabled' => [
                'required_without_all:announcements_enabled,system_enabled,promotion_enabled',
                'boolean',
            ],
        ];
    }
}
