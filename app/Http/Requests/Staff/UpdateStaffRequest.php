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
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('admins', 'email')->whereNull('deleted_at')->ignore($admin->id),
            ],
            'password' => ['nullable', 'string', Password::defaults(), 'confirmed'],
            'status' => ['required', Rule::enum(AdminStatus::class)],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', Rule::exists('roles', 'id')->where('guard_name', 'web')],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('password')) {
            $this->merge([
                'password' => null,
                'password_confirmation' => null,
            ]);
        }
    }
}
