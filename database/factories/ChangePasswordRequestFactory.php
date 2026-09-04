<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\BroadbandAccount;
use App\Models\ChangePasswordRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChangePasswordRequest>
 */
class ChangePasswordRequestFactory extends Factory
{
    protected $model = ChangePasswordRequest::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'broadband_account_id' => BroadbandAccount::factory(),
            'contact_name' => fake()->name(),
            'contact_phone' => fake()->phoneNumber(),
            'new_wifi_name' => fake()->userName() . '_wifi',
            'new_password' => fake()->password(8, 16),
            'status' => 'under_review',
            'admin_id' => null,
        ];
    }

    /**
     * Request is still awaiting review.
     */
    public function underReview(): static
    {
        return $this->state(
            fn(array $attributes) => [
                'status' => 'under_review',
                'admin_id' => null,
            ],
        );
    }

    /**
     * Request was approved by an admin.
     */
    public function approved(): static
    {
        return $this->state(
            fn(array $attributes) => [
                'status' => 'approved',
                'admin_id' => User::factory(),
            ],
        );
    }

    /**
     * Request was rejected by an admin.
     */
    public function rejected(): static
    {
        return $this->state(
            fn(array $attributes) => [
                'status' => 'rejected',
                'admin_id' => User::factory(),
            ],
        );
    }
}
