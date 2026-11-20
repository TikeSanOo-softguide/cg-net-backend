<?php

namespace Tests\Feature;

use App\Enums\SecurityLogEvent;
use App\Models\Admin;
use App\Models\SecurityLog;
use App\Services\SecurityLog\SecurityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
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
                    ->component('Logs/SecurityLog/index')
                    ->where('filterOptions.event', SecurityLogEvent::values())
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

    public function test_unauthorized_profile_request_stores_device_metadata(): void
    {
        $this->getJson('/api/customer/profile', [
            'X-Device-Id' => '2616a47c-d2e3-4e82-a269-c52eba7130ab',
            'X-Device-Name' => 'Web',
            'X-Platform' => 'web',
        ])->assertUnauthorized();

        $log = SecurityLog::query()->firstOrFail();

        $this->assertSame('unauthorized_access', $log->event);
        $this->assertSame('Unauthenticated access attempt', $log->metadata['reason']);
        $this->assertSame(401, $log->metadata['status_code']);
        $this->assertSame('2616a47c-d2e3-4e82-a269-c52eba7130ab', $log->metadata['device_id']);
        $this->assertSame('Web', $log->metadata['device_name']);
        $this->assertSame('web', $log->metadata['platform']);
    }

    public function test_security_log_stores_device_name_and_exposes_metadata(): void
    {
        $admin = Admin::factory()->create();
        $request = Request::create('/api/login', 'POST', server: [
            'HTTP_X_DEVICE_ID' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'HTTP_X_DEVICE_NAME' => "Realme\nRMX3710",
            'HTTP_X_PLATFORM' => 'android',
        ]);

        app(SecurityLogService::class)->record(
            event: SecurityLogEvent::LoginFailed,
            metadata: ['reason' => 'Invalid credentials'],
            request: $request,
        );

        $this->actingAs($admin, 'web')
            ->get('/logs/security')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->has('logs.data', 1)
                    ->where('logs.data.0.metadata.device_name', 'Realme RMX3710')
                    ->where('logs.data.0.metadata.device_id', 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee')
                    ->where('logs.data.0.metadata.platform', 'android')
                    ->where('logs.data.0.metadata.reason', 'Invalid credentials'),
            );
    }
}
