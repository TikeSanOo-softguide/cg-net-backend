<?php

namespace Database\Factories;

use App\Enums\LanguagePref;
use App\Enums\UserStatus;
use App\Models\User;
use Database\Factories\Support\MyanmarFake;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'phone' => MyanmarFake::phone(),
            'name' => MyanmarFake::name(),
            'nrc_number' => MyanmarFake::nrc(),
            'email' => fake()->optional(0.7)->safeEmail(),
            'address' => MyanmarFake::address(),
            'language_pref' => fake()->randomElement(LanguagePref::cases()),
            'status' => UserStatus::Active,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => [
            'status' => UserStatus::Suspended,
        ]);
    }
}
