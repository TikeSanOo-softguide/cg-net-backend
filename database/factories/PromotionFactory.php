<?php

namespace Database\Factories;

use App\Enums\LanguagePref;
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
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'is_active' => true,
            'image_path' => 'cms/promotions/'.fake()->uuid().'.jpg',
            'lang' => LanguagePref::En,
        ];
    }
}
