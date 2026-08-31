<?php

namespace Database\Factories;

use App\Models\Speed;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Speed>
 */
class SpeedFactory extends Factory
{
    public function definition(): array
    {
        return [
            'mbps' => fake()->randomElement([10, 20, 30, 50, 100, 200, 300, 500, 1000]),
        ];
    }
}
