<?php

namespace Database\Factories;

use App\Enums\ConnectionStatus;
use App\Models\CpeDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CpeDevice>
 */
class CpeDeviceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'cpe_identifier' => strtoupper(fake()->unique()->bothify('CG-CPE-####??')),
            'ssid' => 'CGNET-' . fake()->bothify('????##'),
            'wifi_password' => fake()->password(10, 14),
            'connection_status' => fake()->randomElement(ConnectionStatus::cases()),
        ];
    }
}
