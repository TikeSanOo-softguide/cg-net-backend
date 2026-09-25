<?php

namespace App\Http\Requests\Region;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $state = $this->route('state');
        return [
            'name_en' => ['required', 'string', 'max:50', Rule::unique('states', 'name_en')->ignore($state->id)->withoutTrashed()],
            'name_my' => ['required', 'string', 'max:50', Rule::unique('states', 'name_my')->ignore($state->id)->withoutTrashed()],
            'name_zh' => ['required', 'string', 'max:50', Rule::unique('states', 'name_zh')->ignore($state->id)->withoutTrashed()],
            'latitude' => ['required', 'numeric', 'between:-90,90', 'decimal:0,7'],
            'longitude' => ['required', 'numeric', 'between:-180,180', 'decimal:0,7'],
        ];
    }
}
