<?php

namespace App\Http\Requests\Customer;

use App\Enums\UserStatus;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Validation\Rule;

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
                'regex:' . PhoneNumber::SUPPORTED_PATTERN,
                Rule::unique('users', 'phone')->ignore($ignore),
            ],
            'password' => $customer === null
                ? ['required', 'string', 'regex:/^\d{6}$/', 'confirmed']
                : ['nullable', 'string', 'regex:/^\d{6}$/', 'confirmed'],
            'password_confirmation' => $customer === null
                ? ['required', 'string']
                : ['nullable', 'string'],
            'status' => [
                'required',
                Rule::in([
                    UserStatus::Active->value,
                    UserStatus::Suspended->value,
                    ...($customer?->status === UserStatus::Deactivated ? [UserStatus::Deactivated->value] : []),
                ]),
            ],
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
            'password.regex' => __('customers.validation.password_min'),
            'password.confirmed' => __('customers.validation.password_confirmation_mismatch'),
            'password_confirmation.required' => __('customers.validation.password_confirmation_required'),
            'status.required' => __('customers.validation.status_required'),
            'status.in' => __('customers.validation.status_required'),
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

    /**
     * Admins may type +959…, 09… or 959…; all are stored in the canonical format
     * (no "+"), the same one the mobile app uses, so one customer = one spelling.
     */
    public static function preparePhone(mixed $phone): ?string
    {
        return PhoneNumber::normalizeLeniently($phone);
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
