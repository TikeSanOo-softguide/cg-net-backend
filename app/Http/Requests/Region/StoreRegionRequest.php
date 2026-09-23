<?php

namespace App\Http\Requests\Region;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRegionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     *  @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name_en' => [
                'required',
                'string',
                'max:50',
                Rule::unique('regions', 'name_en')->where('state_id', $this->state_id)->withoutTrashed(),
            ],
            'name_my' => [
                'required',
                'string',
                'max:50',
                Rule::unique('regions', 'name_my')->where('state_id', $this->state_id)->withoutTrashed(),
            ],
            'name_zh' => [
                'required',
                'string',
                'max:50',
                Rule::unique('regions', 'name_zh')->where('state_id', $this->state_id)->withoutTrashed(),
            ],
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90', 'decimal:0,7'],
            'longitude' => ['required', 'numeric', 'between:-180,180', 'decimal:0,7'],
        ];
    }
}
