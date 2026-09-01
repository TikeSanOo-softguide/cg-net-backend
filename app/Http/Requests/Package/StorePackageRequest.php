<?php

namespace App\Http\Requests\Package;

use App\Http\Requests\Cms\CmsRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePackageRequest extends FormRequest
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
            'network_id' => ['required', 'integer', 'exists:networks,id'],
            'speed_id' => ['required', 'integer', 'exists:speeds,id'],
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'installation_fee' => ['required', 'numeric', 'min:0'],
            'includes_free_iptv' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'recommended' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'image_url' => CmsRules::image(false),
        ];
    }
}
