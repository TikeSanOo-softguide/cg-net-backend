<?php

namespace App\Http\Requests\Region;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $area = $this->route('area');

        return [
            'name_en' => [
                'required',
                'string',
                'max:50',
                Rule::unique('areas', 'name_en')
                    ->where(fn($query) => $query->where('region_id', $this->region_id))
                    ->ignore($area->id)
                    ->withoutTrashed(),
            ],
            'name_my' => [
                'required',
                'string',
                'max:50',
                Rule::unique('areas', 'name_my')
                    ->where(fn($query) => $query->where('region_id', $this->region_id))
                    ->ignore($area->id)
                    ->withoutTrashed(),
            ],
            'name_zh' => [
                'required',
                'string',
                'max:50',
                Rule::unique('areas', 'name_zh')
                    ->where(fn($query) => $query->where('region_id', $this->region_id))
                    ->ignore($area->id)
                    ->withoutTrashed(),
            ],
            'region_id' => ['required', 'exists:regions,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90', 'decimal:0,7'],
            'longitude' => ['required', 'numeric', 'between:-180,180', 'decimal:0,7'],
        ];
    }
}
