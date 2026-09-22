<?php

namespace Database\Factories;

use App\Enums\WalletStatus;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'balance' => fake()->numberBetween(0, 150000),
            'status' => WalletStatus::Active,
            'version' => 1,
        ];
    }
}
