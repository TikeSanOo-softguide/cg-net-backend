<?php

namespace Database\Factories;

use App\Models\Package;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Voucher>
 */
class VoucherFactory extends Factory
{
    public function definition(): array
    {
        return [
            'serial_number' => strtoupper(fake()->unique()->bothify('VCH########')),
            'pin_hash' => Hash::make(fake()->numerify('########')),
            'package_id' => fake()->boolean(70) ? Package::factory() : null,
            'amount' => fake()->optional()->randomElement([5000, 10000, 20000]),
            'is_redeemed' => false,
            'redeemed_by_user_id' => null,
            'redeemed_at' => null,
        ];
    }

    public function redeemed(?User $user = null): static
    {
        return $this->state(fn () => [
            'is_redeemed' => true,
            'redeemed_by_user_id' => $user?->id ?? User::factory(),
            'redeemed_at' => now()->subDays(fake()->numberBetween(1, 20)),
        ]);
    }
}
