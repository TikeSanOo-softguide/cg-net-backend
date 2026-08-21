<?php

namespace Database\Factories;

use App\Enums\LanguagePref;
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
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('###'),
            'content' => fake()->paragraphs(3, true),
            'image_path' => 'cms/news/'.fake()->uuid().'.jpg',
            'status' => NewsStatus::Draft,
            'lang' => LanguagePref::En,
        ];
    }
}
