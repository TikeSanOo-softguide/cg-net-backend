<?php

namespace Database\Factories;

use App\Enums\LanguagePref;
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
            'image_path' => 'cms/gallery/'.fake()->uuid().'.jpg',
            'label' => fake()->words(3, true),
            'lang' => LanguagePref::En,
        ];
    }
}
