<?php

namespace App\Http\Requests\ServiceRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateBroadbandApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'package_id' => ['required', 'integer', Rule::exists('packages', 'id')->where('is_active', true)],
            'area_id' => ['required', 'integer', Rule::exists('areas', 'id')],
            'id_type' => ['required', 'string', 'max:16'],
            'id_name' => ['required', 'string', 'max:255'],
            'id_number' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:5000'],
            'phone' => ['required', 'string', 'max:16'],
            'note' => ['nullable', 'string', 'max:5000'],
            'photos' => ['required', 'array', 'size:2'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }
}
