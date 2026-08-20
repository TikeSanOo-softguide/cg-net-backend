<?php

namespace Database\Factories;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        $status = fake()->randomElement(PaymentStatus::cases());

        return [
            'invoice_id' => Invoice::factory(),
            'gateway' => fake()->randomElement(PaymentGateway::cases()),
            'gateway_ref' => strtoupper(fake()->unique()->bothify('GW########')),
            'amount' => fake()->randomElement([15000, 25000, 35000]),
            'status' => $status,
            'paid_at' => $status === PaymentStatus::Paid ? now() : null,
        ];
    }
}
