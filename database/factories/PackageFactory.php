<?php

namespace Database\Factories;

use App\Models\Network;
use App\Models\Package;
use App\Models\Speed;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'network_id' => Network::inRandomOrder()->value('id'),
            'speed_id' => Speed::factory(),
            'term_id' => Term::factory(),

            'price' => fake()->randomElement([15000, 25000, 35000, 45000, 65000, 99000]),

            'image_url' => fake()->optional()->imageUrl(),

            'installation_fee' => fake()->randomElement([0, 5000, 10000]),

            'includes_free_iptv' => fake()->boolean(),
            'is_active' => true,
            'recommended' => fake()->boolean(20),
            'sort_order' => fake()->numberBetween(0, 100),
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

    public function recommended(): static
    {
        return $this->state(
            fn() => [
                'recommended' => true,
            ],
        );
    }

    public function withFreeIptv(): static
    {
        return $this->state(
            fn() => [
                'includes_free_iptv' => true,
            ],
        );
    }
}
