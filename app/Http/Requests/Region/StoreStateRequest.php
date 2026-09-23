<?php

namespace App\Http\Requests\Region;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:50', Rule::unique('states', 'name_en')->withoutTrashed()],
            'name_my' => ['required', 'string', 'max:50', Rule::unique('states', 'name_my')->withoutTrashed()],
            'name_zh' => ['required', 'string', 'max:50', Rule::unique('states', 'name_zh')->withoutTrashed()],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'decimal:0,7'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'decimal:0,7'],
        ];
    }
}
