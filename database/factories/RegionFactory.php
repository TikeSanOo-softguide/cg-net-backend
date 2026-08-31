<?php

namespace Database\Factories;

use App\Models\Region;
use App\Models\State;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Region>
 */
class RegionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name_en' => fake()->city(),
            'name_zh' => fake()->city(),
            'name_my' => fake()->city(),
            'state_id' => State::factory(),
        ];
    }
}
