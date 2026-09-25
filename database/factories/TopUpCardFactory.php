<?php

namespace Database\Factories;

use App\Enums\TopUpCardStatus;
use App\Models\TopUpCard;
use App\Models\User;
use App\Support\GeneratesTopUpCards;
use App\Support\TopUpCardPin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TopUpCard>
 */
class TopUpCardFactory extends Factory
{
    public function definition(): array
    {
        $amount = (string) fake()->randomElement([50, 100, 250, 500]);
        $serialNo = GeneratesTopUpCards::serialNo($amount);
        $pin = GeneratesTopUpCards::pin();

        return [
            'serial_no' => $serialNo,
            'pin' => TopUpCardPin::hash($pin),
            'amount' => (int) $amount,
            'expires_at' => now()->addYears(2),
            'redeemed_at' => null,
            'redeemed_by' => null,
            'status' => TopUpCardStatus::Active,
            'batch_id' => \App\Models\Batch::factory(),
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
