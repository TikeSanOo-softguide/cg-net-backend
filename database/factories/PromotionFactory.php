<?php

namespace Database\Factories;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title_en' => fake()->sentence(4),
            'title_zh' => fake()->sentence(4),
            'title_my' => fake()->sentence(4),
            'description_en' => fake()->paragraph(),
            'description_zh' => fake()->paragraph(),
            'description_my' => fake()->paragraph(),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'is_active' => true,
            'image_url' => fake()->imageUrl(),
            'slug' => fake()->unique()->slug(),
        ];
    }
}
