<?php

namespace Database\Factories;

use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Term>
 */
class TermFactory extends Factory
{
    public function definition(): array
    {
        return [
            'months' => fake()->randomElement([1, 3, 6, 12]),
        ];
    }
}
