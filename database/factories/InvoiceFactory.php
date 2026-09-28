<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $amount = fake()->randomElement([15000, 25000, 35000, 45000]);

        return [
            'user_id' => User::factory(),
            'invoice_no' => 'INV-' . now()->format('Ym') . '-' . fake()->unique()->numerify('#####'),
            'amount' => $amount,
            'due_date' => fake()->dateTimeBetween('now', '+20 days'),
            'status' => InvoiceStatus::Unpaid,
            'plan_snapshot' => [
                'name' => 'Home 100GB',
                'price' => $amount,
                'speed_mbps' => 50,
            ],
        ];
    }
}
