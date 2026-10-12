<?php

namespace Database\Factories;

use App\Enums\NotificationCategory;
use App\Enums\NotificationTemplateType;
use App\Models\Notification;
use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    public function definition(): array
    {
        $template = NotificationTemplate::query()->firstOrCreate(
            ['type' => NotificationTemplateType::BillAlert],
            (new NotificationTemplateFactory)->definition(),
        );

        return [
            'user_id' => fake()->boolean(70) ? User::factory() : null,
            'category' => NotificationCategory::BillAlert,
            'is_read' => fake()->boolean(30),
            'sent_at' => now()->subHours(fake()->numberBetween(1, 72)),
            'templateable_type' => NotificationTemplate::class,
            'templateable_id' => $template->id,
            'template_data' => [
                'account_number' => fake()->numerify('########'),
                'due_date' => fake()->date(),
            ],
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
