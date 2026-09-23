<?php

namespace Database\Factories;

use App\Models\Batch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Batch>
 */
class BatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_no' => fake()->unique()->numerify('######'),
            'amount' => fake()->randomElement([50, 100, 250, 500]),
            'quantity' => fake()->numberBetween(1, 100),
            'status' => 'active',
            'expires_at' => now()->addYears(2)->toDateString(),
        ];
    }
}
