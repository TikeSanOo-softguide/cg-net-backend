<?php

namespace Database\Factories;

use App\Models\Addon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Addon>
 */
class AddonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'IPTV Package',
                'Static IP',
                'Extra Router',
                'Wi-Fi Extender',
                'Premium Support',
                'Additional Device',
            ]),
            'price' => fake()->randomElement([5000, 10000, 15000, 20000, 25000]),
            'image_url' => fake()->optional()->imageUrl(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(
            fn() => [
                'is_active' => false,
            ],
        );
    }
}
