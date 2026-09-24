<?php

namespace App\Http\Requests\Package;

use App\Http\Requests\Cms\CmsRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAddonsRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string', 'max:255', Rule::unique('addons', 'name_en')->withoutTrashed()],
            'name_zh' => ['required', 'string', 'max:255', Rule::unique('addons', 'name_zh')->withoutTrashed()],
            'name_my' => ['required', 'string', 'max:255', Rule::unique('addons', 'name_my')->withoutTrashed()],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'image_url' => CmsRules::image(false),
        ];
    }
}
