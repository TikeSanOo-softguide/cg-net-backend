<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    public function definition(): array
    {
        $gb = fake()->randomElement([50, 100, 200, 300, 500, 1000]);
        $speed = fake()->randomElement([20, 30, 50, 100, 200]);

        return [
            'name' => $gb.'GB / '.$speed.' Mbps',
            'data_gb' => $gb,
            'price' => fake()->randomElement([15000, 25000, 35000, 45000, 65000, 99000]),
            'validity_days' => fake()->randomElement([30, 90, 365]),
            'speed_mbps' => $speed,
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
