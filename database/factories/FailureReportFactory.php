<?php

namespace Database\Factories;

use App\Enums\FailureType;
use App\Enums\RequestStatus;
use App\Models\FailureReport;
use App\Models\User;
use Database\Factories\Support\MyanmarFake;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FailureReport>
 */
class FailureReportFactory extends Factory
{
    public function definition(): array
    {
        $accountNumber = 'CG' . fake()->unique()->numerify('########');

        return [
            'user_id' => User::factory()->state(['broadband_account_number' => $accountNumber]),
            'broadband_account_number' => $accountNumber,
            'failure_type' => fake()->randomElement(FailureType::cases()),
            'description' => fake()->paragraph(),
            'contact_name' => MyanmarFake::name(),
            'contact_phone' => MyanmarFake::phone(),
            'status' => fake()->randomElement(RequestStatus::cases()),
            'admin_id' => 1,
        ];
    }
}
