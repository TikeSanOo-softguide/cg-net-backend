<?php

namespace App\Http\Requests\Customer;

use App\Enums\LanguagePref;
use App\Enums\UserStatus;
use App\Models\User;
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
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:16', 'regex:/^\+?[0-9]{8,15}$/', Rule::unique('users', 'phone')->ignore($ignore)],
            'nrc_number' => ['required', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignore)],
            'address' => ['nullable', 'string', 'max:1000'],
            'language_pref' => ['required', Rule::enum(LanguagePref::class)],
            'status' => ['required', Rule::enum(UserStatus::class)],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{name: string, phone: string, nrc_number: string, email: string|null, address: string|null, language_pref: string, status: string}
     */
    public static function payload(array $validated): array
    {
        $email = isset($validated['email']) ? trim((string) $validated['email']) : '';
        $address = isset($validated['address']) ? trim((string) $validated['address']) : '';

        return [
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'nrc_number' => $validated['nrc_number'],
            'email' => $email !== '' ? $email : null,
            'address' => $address !== '' ? $address : null,
            'language_pref' => $validated['language_pref'],
            'status' => $validated['status'],
        ];
    }
}
