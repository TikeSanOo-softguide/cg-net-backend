<?php

namespace Database\Factories;

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
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'note' => fake()->optional()->sentence(),
            'status' => fake()->randomElement(['pending', 'approved', 'rejected', 'installed', 'cancelled']),
            'user_id' => User::factory(),
        ];
    }

    public function pending(): static
    {
        return $this->state(
            fn() => [
                'status' => 'pending',
            ],
        );
    }

    public function approved(): static
    {
        return $this->state(
            fn() => [
                'status' => 'approved',
            ],
        );
    }

    public function rejected(): static
    {
        return $this->state(
            fn() => [
                'status' => 'rejected',
            ],
        );
    }

    public function installed(): static
    {
        return $this->state(
            fn() => [
                'status' => 'installed',
            ],
        );
    }

    public function cancelled(): static
    {
        return $this->state(
            fn() => [
                'status' => 'cancelled',
            ],
        );
    }
}
