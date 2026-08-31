<?php

namespace Database\Factories;

use App\Models\State;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<State>
 */
class StateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name_en' => fake()->unique()->state(),
            'name_zh' => fake()->unique()->state(),
            'name_my' => fake()->unique()->state(),
        ];
    }
}
