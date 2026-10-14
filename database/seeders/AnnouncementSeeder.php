<?php

namespace Database\Seeders;

use App\Models\Announcement;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AnnouncementSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        echo "Announcement seeder started\n";

        Announcement::factory()->active()->create();
        Announcement::factory()->inactive()->create();
        Announcement::factory()->upcoming()->create();
        Announcement::factory()->expired()->create();
    }
}
