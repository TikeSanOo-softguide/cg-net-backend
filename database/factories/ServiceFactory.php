<?php

namespace Database\Factories;

use App\Enums\ServiceStatus;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(6);

        return [
            'title_en' => $title,
            'title_zh' => $title,
            'title_my' => $title,
            'slug' => Str::slug($title) . '-' . fake()->unique()->numerify('###'),
            'description_en' => fake()->paragraphs(3, true),
            'description_zh' => fake()->paragraphs(3, true),
            'description_my' => fake()->paragraphs(3, true),
            'image_url' => fake()->imageUrl(),
            'status' => ServiceStatus::Draft,
        ];
    }
}
