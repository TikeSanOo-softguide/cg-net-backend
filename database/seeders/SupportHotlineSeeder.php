<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SupportHotlineSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        echo "Support hotline seeder started\n";

        Setting::factory()->create([
            'key' => 'support_hotline',
            'value' => '+959123456789',
        ]);
    }
}
