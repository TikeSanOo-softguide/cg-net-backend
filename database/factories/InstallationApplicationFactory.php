<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\Area;
use App\Models\InstallationApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstallationApplication>
 */
class InstallationApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'area_id' => Area::factory(),
            'name' => fake()->name(),
            'service_type' => 'FTTH',
            'ticket_number' => 'INT'.now()->format('md').str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'package_detail' => [
                'product_id' => (string) fake()->numberBetween(1, 999),
                'product_name' => fake()->words(3, true),
                'product_line' => fake()->word(),
                'product_speed' => fake()->numberBetween(10, 200).'M',
                'product_price' => fake()->numberBetween(1000, 50000),
                'installation_fee' => fake()->numberBetween(0, 5000),
                'month_cycle' => 1,
                'month_cycle_unit' => '月',
            ],
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'note' => fake()->optional()->sentence(),
            'status' => fake()->randomElement([
                RequestStatus::Approved,
                RequestStatus::UnderReview,
                RequestStatus::Cancelled,
            ]),
            'user_id' => User::factory(),
        ];
    }
}
