<?php

namespace Database\Factories;

use App\Enums\CustomerPackageStatus;
use App\Models\BroadbandAccount;
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
            'broadband_account_id' => BroadbandAccount::factory(),
            'package_id' => Package::factory(),
            'start_date' => $start,
            'expiry_date' => (clone $start)->modify('+30 days'),
            'auto_renew' => fake()->boolean(40),
            'status' => CustomerPackageStatus::Active,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => CustomerPackageStatus::Expired,
            'expiry_date' => now()->subDays(10),
        ]);
    }
}
