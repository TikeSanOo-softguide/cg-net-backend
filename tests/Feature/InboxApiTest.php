<?php

namespace Tests\Feature;

use App\Enums\AnnouncementType;
use App\Models\Announcement;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboxApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbox_lists_current_announcements_and_promotions(): void
    {
        Announcement::factory()->create([
            'type' => AnnouncementType::Announce,
            'title_en' => 'Announce title',
            'is_active' => true,
            'start_date' => now()->subHour(),
            'end_date' => now()->addDay(),
        ]);
        Announcement::factory()->create([
            'type' => AnnouncementType::System,
            'title_en' => 'System title',
            'is_active' => true,
            'start_date' => now()->subHour(),
            'end_date' => now()->addDay(),
        ]);
        Announcement::factory()->inactive()->create([
            'title_en' => 'Hidden announcement',
            'start_date' => now()->subHour(),
            'end_date' => now()->addDay(),
        ]);
        Promotion::factory()->create([
            'title_en' => 'Promo title',
            'is_active' => true,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);
        Promotion::factory()->create([
            'title_en' => 'Old promo',
            'is_active' => true,
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
        ]);

        $titles = collect($this->getJson('/api/inbox')->assertOk()->json('data'))
            ->pluck('title.en');

        $this->assertTrue($titles->contains('Announce title'));
        $this->assertTrue($titles->contains('System title'));
        $this->assertTrue($titles->contains('Promo title'));
        $this->assertFalse($titles->contains('Hidden announcement'));
        $this->assertFalse($titles->contains('Old promo'));
    }
}
