<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\BroadbandAccount;
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
        return [
            'user_id' => User::factory(),
            'broadband_account_id' => BroadbandAccount::factory(),
            'current_address' => MyanmarFake::address(),
            'new_address' => MyanmarFake::address(),
            'preferred_date' => fake()->dateTimeBetween('now', '+30 days'),
            'phone' => MyanmarFake::phone(),
            'details' => fake()->optional()->sentence(),
            'status' => fake()->randomElement(ReviewStatus::cases()),
        ];
    }
}
