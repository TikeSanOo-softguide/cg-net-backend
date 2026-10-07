<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
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
        $accountNumber = 'CG' . fake()->unique()->numerify('########');

        return [
            'user_id' => User::factory()->state(['broadband_account_number' => $accountNumber]),
            'broadband_account_number' => $accountNumber,
            'current_package_id' => Package::factory(),
            'new_package_id' => Package::factory(),
            'preferred_date' => fake()->dateTimeBetween('now', '+20 days'),
            'contact_name' => MyanmarFake::name(),
            'contact_phone' => MyanmarFake::phone(),
            'note' => fake()->optional()->sentence(),
            'status' => fake()->randomElement(RequestStatus::cases()),
        ];
    }
}
