<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\InstallationApplication;
use App\Models\Package;
use App\Models\Region;
use App\Models\User;
use Database\Factories\Support\MyanmarFake;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstallationApplication>
 */
class InstallationApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'region_id' => Region::factory(),
            'id_type' => fake()->randomElement(['nrc', 'passport']),
            'id_number' => MyanmarFake::nrc(),
            'plan_id' => Package::factory(),
            'photo_path' => 'installations/'.fake()->uuid().'.jpg',
            'latitude' => fake()->latitude(16.7, 22.0),
            'longitude' => fake()->longitude(96.0, 97.2),
            'address' => MyanmarFake::address(),
            'note' => fake()->optional()->sentence(),
            'status' => fake()->randomElement(ReviewStatus::cases()),
        ];
    }
}
