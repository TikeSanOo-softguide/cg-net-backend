<?php

namespace Database\Factories;

use App\Enums\BroadbandAccountStatus;
use App\Models\BroadbandAccount;
use App\Models\Package;
use App\Models\User;
use Database\Factories\Support\MyanmarFake;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BroadbandAccount>
 */
class BroadbandAccountFactory extends Factory
{
    public function definition(): array
    {
        $name = MyanmarFake::name();

        return [
            'user_id' => User::factory(),
            'account_number' => 'CG'.fake()->unique()->numerify('########'),
            'customer_name' => $name,
            'status' => BroadbandAccountStatus::Active,
            'current_package_id' => Package::factory(),
        ];
    }

    public function unbound(): static
    {
        return $this->state(fn () => [
            'user_id' => null,
        ]);
    }
}
