<?php

namespace Database\Seeders;

use App\Models\Notification;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        echo "Notification seeder started\n";

        Notification::factory()->count(4)->create(['is_read' => false, 'user_id' => null]);
        Notification::factory()->count(2)->create(['is_read' => true, 'user_id' => null]);
    }
}
