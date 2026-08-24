<?php

namespace Database\Factories;

use App\Enums\NewsStatus;
use App\Models\Category;
use App\Models\News;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<News>
 */
class NewsFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(6);

        return [
            'category_id' => Category::factory(),
            'title_en' => $title,
            'title_zh' => $title,
            'title_my' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('###'),
            'description_en' => fake()->paragraphs(3, true),
            'description_zh' => fake()->paragraphs(3, true),
            'description_my' => fake()->paragraphs(3, true),
            'image_url' => fake()->imageUrl(),
            'status' => NewsStatus::Draft,
        ];
    }
}
