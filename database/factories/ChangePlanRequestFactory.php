<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\ChangePlanRequest;
use App\Models\User;
use Database\Factories\Support\MyanmarFake;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChangePlanRequest>
 */
class ChangePlanRequestFactory extends Factory
{
    public function definition(): array
    {
        $accountNumber = 'CG' . fake()->unique()->numerify('########');

        return [
            'user_id' => User::factory()->state(['broadband_account_number' => $accountNumber]),
            'broadband_account_number' => $accountNumber,
            'service_type' => fake()->randomElement(['FTTH', 'CG']),
            'ticket_number' => 'CPL'.now()->format('md').str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'current_package_detail' => [
                'price' => fake()->numberBetween(10000, 40000),
                'network_name' => ['en' => 'CG', 'my' => 'CG', 'zh' => 'CG'],
                'speed' => ['mbps' => fake()->randomElement([10, 20, 30])],
                'term' => ['months' => fake()->randomElement([1, 3, 6, 12])],
            ],
            'new_package_detail' => [
                'price' => fake()->numberBetween(15000, 80000),
                'network_name' => ['en' => 'CG', 'my' => 'CG', 'zh' => 'CG'],
                'speed' => ['mbps' => fake()->randomElement([20, 30, 50])],
                'term' => ['months' => fake()->randomElement([1, 3, 6, 12])],
            ],
            'preferred_date' => fake()->dateTimeBetween('now', '+20 days'),
            'contact_name' => MyanmarFake::name(),
            'contact_phone' => MyanmarFake::phone(),
            'note' => fake()->optional()->sentence(),
            'status' => fake()->randomElement(RequestStatus::cases()),
        ];
    }
}
