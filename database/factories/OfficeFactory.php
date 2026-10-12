<?php

namespace Database\Factories;

use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Office>
 */
class OfficeFactory extends Factory
{
    protected $model = Office::class;

    public function definition(): array
    {
        static $codes = [];

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $cd = str_pad((string) fake()->numberBetween(10, 99), 2, '0', STR_PAD_LEFT);

            if (isset($codes[$cd]) || Office::query()->where('cd', $cd)->exists()) {
                continue;
            }

            $codes[$cd] = true;

            return [
                'name' => fake()->company() . ' Office',
                'address' => fake()->streetAddress(),
                'cd' => $cd,
            ];
        }

        throw new \RuntimeException('Unable to generate a unique office code.');
    }
}
