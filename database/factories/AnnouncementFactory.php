<?php

namespace Database\Factories;

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-7 days', '+7 days');
        $endDate = (clone $startDate)->modify('+' . fake()->numberBetween(1, 30) . ' days');

        return [
            'content_en' => fake()->paragraph(),
            'content_zh' => fake()->paragraph(),
            'content_my' => fake()->paragraph(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(
            fn() => [
                'is_active' => false,
            ],
        );
    }

    public function active(): static
    {
        return $this->state(
            fn() => [
                'is_active' => true,
            ],
        );
    }

    public function upcoming(): static
    {
        $startDate = now()->addDays(fake()->numberBetween(1, 7));

        return $this->state(
            fn() => [
                'start_date' => $startDate,
                'end_date' => (clone $startDate)->addDays(fake()->numberBetween(1, 30)),
                'is_active' => true,
            ],
        );
    }

    public function expired(): static
    {
        $endDate = now()->subDays(fake()->numberBetween(1, 30));

        return $this->state(
            fn() => [
                'start_date' => (clone $endDate)->subDays(fake()->numberBetween(1, 30)),
                'end_date' => $endDate,
            ],
        );
    }
}
