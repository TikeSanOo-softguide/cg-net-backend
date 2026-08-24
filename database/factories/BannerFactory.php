<?php

namespace Database\Factories;

use App\Models\Banner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'image_url_en' => fake()->imageUrl(),
            'image_url_zh' => fake()->imageUrl(),
            'image_url_my' => fake()->imageUrl(),
            'sort_order' => fake()->numberBetween(1, 20),
            'is_active' => true,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
        ];
    }
}
