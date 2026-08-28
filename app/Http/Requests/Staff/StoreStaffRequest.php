<?php

namespace App\Http\Requests\Staff;

use App\Enums\AdminStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[A-Za-z][A-Za-z0-9]*(?:[ ._ -][A-Za-z0-9]+)*$/',
                Rule::unique('admins', 'username')->whereNull('deleted_at'),
            ],
            'password' => ['required', 'string', 'min:8', Password::defaults(), 'confirmed'],
            'password_confirmation' => ['required', 'string'],
            'status' => ['required', Rule::enum(AdminStatus::class)],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', Rule::exists('roles', 'id')->where('guard_name', 'web')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.required' => __('staff.validation.username_required'),
            'username.min' => __('staff.validation.username_min'),
            'username.regex' => __('staff.validation.username_invalid'),
            'username.unique' => __('staff.validation.username_taken'),
            'password.required' => __('staff.validation.password_required'),
            'password.min' => __('staff.validation.password_min'),
            'password.confirmed' => __('staff.validation.password_confirmation_mismatch'),
            'password_confirmation.required' => __('staff.validation.password_confirmation_required'),
            'status.required' => __('staff.validation.status_required'),
            'role_ids.required' => __('staff.validation.roles_required'),
            'role_ids.min' => __('staff.validation.roles_required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'username' => __('staff.username'),
            'password' => __('staff.password'),
            'password_confirmation' => __('staff.password_confirmation'),
            'status' => __('common.status'),
            'role_ids' => __('staff.roles'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => is_string($this->username) ? trim(preg_replace('/\s+/', ' ', $this->username) ?? $this->username) : $this->username,
        ]);
    }
}
