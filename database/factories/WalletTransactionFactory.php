<?php

namespace Database\Factories;

use App\Enums\WalletActorType;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\User;
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
            'transaction_no' => fake()->unique()->bothify('TXN-##########'),
            'type' => fake()->randomElement(WalletTransactionType::cases()),
            'status' => fake()->randomElement(WalletTransactionStatus::cases()),
            'amount' => fake()->numberBetween(1000, 50000),
            'idempotency_key' => fake()->unique()->uuid(),
            'actor_type' => fake()->randomElement(WalletActorType::cases()),
            'actor_id' => User::query()->inRandomOrder()->value('id') ?? User::factory(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
