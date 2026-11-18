<?php

namespace Database\Factories;

use App\Enums\LedgerTransactionStatus;
use App\Enums\LedgerTransactionType;
use App\Enums\TransactionIdPrefix;
use App\Enums\WalletActorType;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Ledger\TransactionIdGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedgerTransaction>
 */
class LedgerTransactionFactory extends Factory
{
    protected $model = LedgerTransaction::class;

    public function definition(): array
    {
        $type = fake()->randomElement(LedgerTransactionType::cases());

        return [
            'wallet_id' => Wallet::factory(),
            'type' => $type,
            'transaction_no' => function (array $attributes): string {
                $type = $attributes['type'] instanceof LedgerTransactionType
                    ? $attributes['type']
                    : LedgerTransactionType::from((string) $attributes['type']);

                return app(TransactionIdGenerator::class)->generate(TransactionIdPrefix::forType($type));
            },
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
