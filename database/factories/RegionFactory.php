<?php

namespace Database\Factories;

use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Region>
 */
class RegionFactory extends Factory
{
    public function definition(): array
    {
        $en = fake()->unique()->city();

        return [
            'name_en' => $en,
            'name_mm' => $en,
            'parent_id' => null,
        ];
    }

    public function township(?Region $parent = null): static
    {
        return $this->state(fn () => [
            'parent_id' => $parent?->id ?? Region::factory(),
        ]);
    }
}
