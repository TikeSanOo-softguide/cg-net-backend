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
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('admins', 'email')->whereNull('deleted_at')],
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
            'name.required' => __('staff.validation.name_required'),
            'name.min' => __('staff.validation.name_min'),
            'name.max' => __('staff.validation.name_max'),
            'email.required' => __('staff.validation.email_required'),
            'email.email' => __('staff.validation.email_invalid'),
            'email.unique' => __('staff.validation.email_taken'),
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
            'name' => __('staff.name'),
            'email' => __('staff.email'),
            'password' => __('staff.password'),
            'password_confirmation' => __('staff.password_confirmation'),
            'status' => __('common.status'),
            'role_ids' => __('staff.roles'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'email' => is_string($this->email) ? strtolower(trim($this->email)) : $this->email,
        ]);
    }
}
