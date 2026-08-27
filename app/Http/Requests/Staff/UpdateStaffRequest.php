<?php

namespace App\Http\Requests\Staff;

use App\Enums\AdminStatus;
use App\Models\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('staff.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Admin $admin */
        $admin = $this->route('admin');

        return [
            'username' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[A-Za-z0-9]+(?:[ ._ -][A-Za-z0-9]+)*$/',
                Rule::unique('admins', 'username')->whereNull('deleted_at')->ignore($admin->id),
            ],
            'password' => ['nullable', 'string', Password::defaults(), 'confirmed'],
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
            'password.confirmed' => __('staff.validation.password_confirmation_mismatch'),
            'status.required' => __('staff.validation.status_required'),
            'role_ids.required' => __('staff.validation.roles_required'),
            'role_ids.min' => __('staff.validation.roles_required'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => is_string($this->username) ? trim(preg_replace('/\s+/', ' ', $this->username) ?? $this->username) : $this->username,
        ]);

        if (! $this->filled('password')) {
            $this->merge([
                'password' => null,
                'password_confirmation' => null,
            ]);
        }
    }
}
