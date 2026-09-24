<?php

namespace App\Http\Requests\Package;

use App\Http\Requests\Cms\CmsRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePackageRequest extends FormRequest
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
            'network_id' => [
                'required',
                'integer',
                'exists:networks,id',
                Rule::unique('packages', 'network_id')
                    ->where(
                        fn($query) => $query
                            ->where('speed_id', $this->input('speed_id'))
                            ->where('term_id', $this->input('term_id')),
                    )
                    ->withoutTrashed()
                    ->ignore($this->route('package')->id),
            ],
            'speed_id' => ['required', 'integer', 'exists:speeds,id'],
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'image_url' => CmsRules::image(false),
            'installation_fee' => ['required', 'numeric', 'min:0'],
            'includes_free_iptv' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'recommended' => ['required', 'boolean'],
        ];
    }
}
