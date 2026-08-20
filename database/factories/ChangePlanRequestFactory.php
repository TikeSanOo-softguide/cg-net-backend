<?php

namespace Database\Factories;

use App\Enums\ChangePlanStatus;
use App\Models\BroadbandAccount;
use App\Models\ChangePlanRequest;
use App\Models\Package;
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
        return [
            'user_id' => User::factory(),
            'broadband_account_id' => BroadbandAccount::factory(),
            'current_package_id' => Package::factory(),
            'new_package_id' => Package::factory(),
            'preferred_date' => fake()->dateTimeBetween('now', '+20 days'),
            'phone' => MyanmarFake::phone(),
            'note' => fake()->optional()->sentence(),
            'status' => fake()->randomElement(ChangePlanStatus::cases()),
        ];
    }
}
