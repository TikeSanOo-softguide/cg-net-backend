<?php

namespace App\Http\Requests\Staff;

use App\Support\AppPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('roles.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80', Rule::unique('roles', 'name')->where('guard_name', 'web')],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in(AppPermissions::names())],
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
            'permissions.required' => __('staff.validation.permissions_required'),
            'permissions.min' => __('staff.validation.permissions_required'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
        ]);
    }
}
