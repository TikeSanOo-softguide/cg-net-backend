<?php

namespace Database\Factories;

use App\Models\FailurePhoto;
use App\Models\FailureReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FailurePhoto>
 */
class FailurePhotoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'failure_report_id' => FailureReport::factory(),
            'image_url' => 'https://images.unsplash.com/photo-1551818255-e6e10975bc17?auto=format&fit=crop&w=1200&q=80',
            'label' => fake()->randomElement(['front_view', 'equipment_box', 'signal_issue', 'cable_line']),
        ];
    }
}
