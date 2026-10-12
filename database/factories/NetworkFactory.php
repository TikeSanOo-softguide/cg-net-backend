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
        $name = fake()->unique()->bothify('Network-????-##');

        return [
            'name_en' => $name,
            'name_zh' => $name,
            'name_my' => $name,
        ];
    }
}
