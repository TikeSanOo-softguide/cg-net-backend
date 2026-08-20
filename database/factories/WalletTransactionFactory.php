<?php

namespace Database\Factories;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WalletTransaction>
 */
class WalletTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'type' => fake()->randomElement(WalletTransactionType::cases()),
            'amount' => fake()->randomFloat(2, 1000, 50000),
            'reference_id' => fake()->optional()->bothify('REF-########'),
            'status' => WalletTransactionStatus::Completed,
        ];
    }
}
