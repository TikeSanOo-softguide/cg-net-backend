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
        $amount = fake()->randomElement([50, 100, 250, 500]);
        $quantity = fake()->numberBetween(1, 100);
        $agentCd = (string) fake()->randomElement(['88', '11', '22']);

        return [
            'batch_no' => now('Asia/Yangon')->format('YmdHisv'),
            'total_value' => $amount * $quantity,
            'quantity' => $quantity,
            'status' => 'active',
            'expires_at' => now()->addYears(2)->toDateString(),
            'metadata' => [
                'items' => [
                    [
                        'agent_cd' => $agentCd,
                        'amount' => $amount,
                        'quantity' => $quantity,
                    ],
                ],
            ],
        ];
    }
}
