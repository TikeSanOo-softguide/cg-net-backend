<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name_en' => fake()->city(),
            'name_zh' => fake()->city(),
            'name_my' => fake()->city(),
            'region_id' => Region::factory(),
        ];
    }
}
