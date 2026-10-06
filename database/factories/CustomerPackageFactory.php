<?php

namespace Database\Factories;

use App\Enums\CustomerPackageStatus;
use App\Models\CustomerPackage;
use App\Models\Package;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerPackage>
 */
class CustomerPackageFactory extends Factory
{
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-6 months', 'now');

        return [
            'user_id' => User::factory(),
            'package_id' => Package::factory(),
            'username' => fake()->unique()->userName(),
            'starts_at' => $start,
            'expires_at' => (clone $start)->modify('+30 days'),
            'status' => CustomerPackageStatus::Active,
        ];
    }

    public function expired(): static
    {
        return $this->state(
            fn () => [
                'status' => CustomerPackageStatus::Expired,
                'expires_at' => now()->subDays(10),
            ],
        );
    }
}
