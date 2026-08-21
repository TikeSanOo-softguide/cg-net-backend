<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'contact_point' => fake()->randomElement([
                fake()->e164PhoneNumber(),
                fake()->companyEmail(),
                'https://facebook.com/'.fake()->userName(),
            ]),
        ];
    }
}
