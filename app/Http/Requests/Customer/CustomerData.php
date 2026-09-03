<?php

namespace App\Http\Requests\Customer;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class CustomerData
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(?User $customer = null): array
    {
        $ignore = $customer?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:16', 'regex:/^\+?[0-9]{8,15}$/', Rule::unique('users', 'phone')->ignore($ignore)],
            'password' => $customer === null
                ? ['required', 'string', 'min:8', Password::defaults(), 'confirmed']
                : ['nullable', 'string', 'min:8', Password::defaults(), 'confirmed'],
            'status' => ['required', Rule::enum(UserStatus::class)],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{name: string, phone: string, status: string, password?: string}
     */
    public static function payload(array $validated): array
    {
        $payload = [
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'status' => $validated['status'],
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = $validated['password'];
        }

        return $payload;
    }
}
