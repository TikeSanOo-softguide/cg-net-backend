<?php

namespace Database\Factories;

use App\Enums\AdminStatus;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'username' => fake()->unique()->regexify('[a-z]{5}[0-9]{3}'),
            'password' => static::$password ??= Hash::make('password'),
            'status' => AdminStatus::Active,
            'remember_token' => Str::random(10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => AdminStatus::Inactive,
        ]);
    }
}
