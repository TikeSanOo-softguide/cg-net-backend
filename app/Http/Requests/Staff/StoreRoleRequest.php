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
            'name' => ['required', 'string', 'max:80', Rule::unique('roles', 'name')->where('guard_name', 'web')],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(AppPermissions::names())],
        ];
    }
}
