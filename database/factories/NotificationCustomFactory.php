<?php

namespace Database\Factories;

use App\Enums\NotificationCategory;
use App\Models\NotificationCustom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationCustom>
 */
class NotificationCustomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => fake()->boolean(70) ? User::factory() : null,
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'category' => fake()->randomElement(NotificationCategory::cases()),
            'is_read' => fake()->boolean(30),
            'sent_at' => now()->subHours(fake()->numberBetween(1, 72)),
        ];
    }

    public function broadcast(): static
    {
        return $this->state(fn () => [
            'user_id' => null,
            'is_read' => false,
        ]);
    }
}
