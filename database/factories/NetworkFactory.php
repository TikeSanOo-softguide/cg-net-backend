<?php

namespace Database\Factories;

use App\Models\Network;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Network>
 */
class NetworkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()
                ->unique()
                ->randomElement(['FTTH', 'Wireless', 'Fiber', '5G']),
        ];
    }
}
