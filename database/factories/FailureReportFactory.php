<?php

namespace Database\Factories;

use App\Enums\FailureType;
use App\Enums\ReviewStatus;
use App\Models\BroadbandAccount;
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
        return [
            'user_id' => User::factory(),
            'broadband_account_id' => BroadbandAccount::factory(),
            'failure_type' => fake()->randomElement(FailureType::cases()),
            'description' => fake()->paragraph(),
            'photo_paths' => ['failures/'.fake()->uuid().'.jpg'],
            'contact_name' => MyanmarFake::name(),
            'contact_phone' => MyanmarFake::phone(),
            'status' => fake()->randomElement(ReviewStatus::cases()),
        ];
    }
}
