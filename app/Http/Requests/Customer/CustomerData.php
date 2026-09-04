<?php

namespace App\Http\Requests\Customer;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

final class CustomerData
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(?User $customer = null): array
    {
        $ignore = $customer?->id;

        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'phone' => [
                'required',
                'string',
                'max:16',
                'regex:/^\+(959\d{7,10}|66\d{8,9}|86\d{11})$/',
                Rule::unique('users', 'phone')->ignore($ignore),
            ],
            'password' => $customer === null
                ? ['required', 'string', 'min:8', Password::defaults(), 'confirmed']
                : ['nullable', 'string', 'min:8', Password::defaults(), 'confirmed'],
            'password_confirmation' => $customer === null
                ? ['required', 'string']
                : ['nullable', 'string'],
            'status' => ['required', Rule::enum(UserStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'name.required' => __('customers.validation.name_required'),
            'name.min' => __('customers.validation.name_min'),
            'name.max' => __('customers.validation.name_max'),
            'phone.required' => __('customers.validation.phone_required'),
            'phone.regex' => __('customers.validation.phone_invalid'),
            'phone.unique' => __('customers.validation.phone_taken'),
            'phone.max' => __('customers.validation.phone_invalid'),
            'password.required' => __('customers.validation.password_required'),
            'password.min' => __('customers.validation.password_min'),
            'password.confirmed' => __('customers.validation.password_confirmation_mismatch'),
            'password_confirmation.required' => __('customers.validation.password_confirmation_required'),
            'status.required' => __('customers.validation.status_required'),
            'status.enum' => __('customers.validation.status_required'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'name' => __('customers.name'),
            'phone' => __('customers.phone'),
            'password' => __('customers.password'),
            'password_confirmation' => __('customers.password_confirmation'),
            'status' => __('common.status'),
        ];
    }

    public static function preparePhone(mixed $phone): ?string
    {
        if (! is_string($phone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        return '+'.$digits;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{name: string, phone: string, status: string, password?: string}
     */
    public static function payload(array $validated): array
    {
        $payload = [
            'name' => trim($validated['name']),
            'phone' => $validated['phone'],
            'status' => $validated['status'],
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = $validated['password'];
        }

        return $payload;
    }
}
