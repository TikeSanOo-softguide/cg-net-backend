<?php

namespace Database\Factories;

use App\Models\Gallery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gallery>
 */
class GalleryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'image_url' => fake()->imageUrl(),
            'label_en' => fake()->words(3, true),
            'label_zh' => fake()->words(3, true),
            'label_my' => fake()->words(3, true),
        ];
    }
}
