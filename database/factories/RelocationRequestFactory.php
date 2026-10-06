<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\RelocationRequest;
use App\Models\User;
use Database\Factories\Support\MyanmarFake;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RelocationRequest>
 */
class RelocationRequestFactory extends Factory
{
    public function definition(): array
    {
        $accountNumber = 'CG' . fake()->unique()->numerify('########');

        return [
            'user_id' => User::factory()->state(['broadband_account_number' => $accountNumber]),
            'broadband_account_number' => $accountNumber,
            'current_address' => MyanmarFake::address(),
            'new_address' => MyanmarFake::address(),
            'preferred_date' => fake()->dateTimeBetween('now', '+30 days'),
            'phone' => MyanmarFake::phone(),
            'details' => fake()->optional()->sentence(),
            'status' => fake()->randomElement(RequestStatus::cases()),
        ];
    }
}
