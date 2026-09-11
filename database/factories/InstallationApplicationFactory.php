<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\Area;
use App\Models\InstallationApplication;
use App\Models\Package;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstallationApplication>
 */
class InstallationApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'package_id' => Package::factory(),
            'area_id' => Area::factory(),
            'id_type' => fake()->randomElement(['NRC', 'Passport', 'Other']),
            'id_name' => fake()->name(),
            'id_number' => fake()->bothify('??######'),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'note' => fake()->optional()->sentence(),
            'status' => fake()->randomElement([
                RequestStatus::Approved,
                RequestStatus::UnderReview,
                RequestStatus::Cancelled,
            ]),
            'user_id' => User::factory(),
        ];
    }
}
