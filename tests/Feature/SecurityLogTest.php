<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\SecurityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class SecurityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_log_index_exposes_and_filters_by_event(): void
    {
        $admin = Admin::factory()->create();

        SecurityLog::query()->create(['event' => 'login_failed']);
        SecurityLog::query()->create(['event' => 'unauthorized_access']);

        $this->actingAs($admin, 'web')
            ->get('/logs/security?event=login_failed')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('SecurityLog/index')
                    ->has('filterOptions.event', 2)
                    ->where('filterOptions.event.0', 'login_failed')
                    ->has('logs.data', 1)
                    ->where('logs.data.0.event', 'login_failed')
                    ->where('filters.event', 'login_failed'),
            );
    }

    public function test_security_log_export_uses_selected_event_filter(): void
    {
        $admin = Admin::factory()->create();

        SecurityLog::query()->create(['event' => 'login_failed']);
        SecurityLog::query()->create(['event' => 'unauthorized_access']);
        Excel::fake();

        $this->actingAs($admin, 'web')->get('/logs/security/export?event=login_failed')->assertOk();

        Excel::assertDownloaded('security-logs.xlsx', function ($export): bool {
            return $export->query()->count() === 1;
        });
    }
}
