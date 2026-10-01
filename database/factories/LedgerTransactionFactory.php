<?php

namespace Database\Factories;

use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\WalletActorType;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedgerTransaction>
 */
class LedgerTransactionFactory extends Factory
{
    protected $model = LedgerTransaction::class;

    public function definition(): array
    {
        return [
            'wallet_id' => Wallet::factory(),
            'transaction_no' => fake()->unique()->bothify('TXN-##########'),
            'type' => fake()->randomElement(LedgerTransactionType::cases()),
            'status' => fake()->randomElement(LedgerTransactionStatus::cases()),
            'amount' => fake()->numberBetween(1000, 50000),
            'idempotency_key' => fake()->unique()->uuid(),
            'actor_type' => fake()->randomElement(WalletActorType::cases()),
            'actor_id' => User::query()->inRandomOrder()->value('id') ?? User::factory(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'posted_at' => now(),
        ];
    }
}
