<?php

namespace Database\Factories;

use App\Enums\TopUpCardStatus;
use App\Models\TopUpCard;
use App\Models\User;
use App\Support\GeneratesTopUpCards;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<TopUpCard>
 */
class TopUpCardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'serial_no' => GeneratesTopUpCards::serialNo((string) fake()->randomElement([50, 100, 250, 500])),
            'pin' => Hash::make(GeneratesTopUpCards::pin()),
            'amount' => fake()->randomElement([50, 100, 250, 500]),
            'expires_at' => now()->addYears(2),
            'redeemed_at' => null,
            'redeemed_by' => null,
            'status' => TopUpCardStatus::Active,
        ];
    }

    public function redeemed(?User $user = null): static
    {
        return $this->state(fn() => [
            'status' => TopUpCardStatus::Used,
            'redeemed_by' => $user?->id ?? User::factory(),
            'redeemed_at' => now()->subDays(fake()->numberBetween(1, 20)),
        ]);
    }

    public function invalid(): static
    {
        return $this->state(fn() => [
            'status' => TopUpCardStatus::Blocked,
        ]);
    }
}
