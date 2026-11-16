<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\Admin;
use App\Models\ChangePasswordRequest;
use App\Models\ChangePlanRequest;
use App\Models\FailureReport;
use App\Models\InstallationApplication;
use App\Models\RelocationRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServiceRequestIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_type_pages_ignore_date_range_query_parameters(): void
    {
        $admin = Admin::factory()->create();
        $pages = [
            ['/service-requests/installations', 'requests', fn(string $date) => InstallationApplication::factory()->create([
                'created_at' => $date,
            ])],
            ['/service-requests/failures', 'reports', fn(string $date) => FailureReport::factory()->create([
                'created_at' => $date,
            ])],
            ['/service-requests/relocations', 'requests', fn(string $date) => RelocationRequest::factory()->create([
                'created_at' => $date,
            ])],
            ['/service-requests/change-plan', 'requests', fn(string $date) => ChangePlanRequest::factory()->create([
                'created_at' => $date,
            ])],
            ['/service-requests/change-password', 'requests', fn(string $date) => ChangePasswordRequest::factory()->create([
                'created_at' => $date,
            ])],
        ];

        foreach ($pages as [$path, $rowsProp, $createRequest]) {
            $createRequest('2026-09-10 12:00:00');
            $createRequest('2026-08-31 12:00:00');

            $this->actingAs($admin, 'web')
                ->get($path . '?from=2026-09-10&to=2026-09-10&status=all')
                ->assertOk()
                ->assertInertia(fn(Assert $page) => $page
                    ->where($rowsProp . '.total', 2)
                    ->where('stats.total_requests', 2));
        }
    }

    public function test_relocation_search_matches_ticket_number(): void
    {
        $admin = Admin::factory()->create();
        RelocationRequest::factory()->create([
            'ticket_number' => 'REL-2026-001',
            'status' => RequestStatus::UnderReview,
        ]);
        RelocationRequest::factory()->create([
            'ticket_number' => 'REL-2026-002',
            'status' => RequestStatus::UnderReview,
        ]);

        $this->actingAs($admin, 'web')
            ->get('/service-requests/relocations?search=REL-2026-001')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->where('requests.total', 1)
                ->where('requests.data.0.ticket_number', 'REL-2026-001'));
    }

    public function test_user_phone_is_searchable_for_every_service_request_type(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create([
            'phone' => '09912345678',
            'broadband_account_number' => 'CG00000123',
        ]);
        $requests = [
            ['/service-requests/installations', 'requests', InstallationApplication::factory()],
            ['/service-requests/failures', 'reports', FailureReport::factory()],
            ['/service-requests/relocations', 'requests', RelocationRequest::factory()],
            ['/service-requests/change-plan', 'requests', ChangePlanRequest::factory()],
            ['/service-requests/change-password', 'requests', ChangePasswordRequest::factory()],
        ];

        foreach ($requests as [$path, $rowsProp, $factory]) {
            $factory->create([
                'user_id' => $user->id,
                'status' => RequestStatus::UnderReview,
            ]);

            $this->actingAs($admin, 'web')
                ->get($path . '?search=09912345678')
                ->assertOk()
                ->assertInertia(fn(Assert $page) => $page->where($rowsProp . '.total', 1));
        }
    }

    public function test_search_does_not_match_failure_description_or_change_password_wifi_name(): void
    {
        $admin = Admin::factory()->create();
        FailureReport::factory()->create([
            'description' => 'failure-description-only-token',
            'status' => RequestStatus::UnderReview,
        ]);
        ChangePasswordRequest::factory()->create([
            'new_wifi_name' => 'wifi-name-only-token',
            'status' => RequestStatus::UnderReview,
        ]);

        $this->actingAs($admin, 'web')
            ->get('/service-requests/failures?search=failure-description-only-token')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page->where('reports.total', 0));

        $this->actingAs($admin, 'web')
            ->get('/service-requests/change-password?search=wifi-name-only-token')
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page->where('requests.total', 0));
    }
}
