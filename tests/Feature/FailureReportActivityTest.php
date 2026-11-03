<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\Admin;
use App\Models\FailureReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FailureReportActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_failure_report_status_changes_are_logged_after_update(): void
    {
        $admin = Admin::factory()->create();
        $failureReport = FailureReport::factory()->create([
            'admin_id' => $admin->id,
            'status' => RequestStatus::UnderReview,
        ]);

        $this->actingAs($admin, 'web')
            ->from(route('service-requests.failures.index'))
            ->patch(route('service-requests.failures.status', $failureReport), [
                'status' => RequestStatus::Approved->value,
            ])
            ->assertRedirect(route('service-requests.failures.index'));

        $this->assertDatabaseHas('failure_reports', [
            'id' => $failureReport->id,
            'status' => RequestStatus::Approved->value,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'failure_report',
            'description' => RequestStatus::Approved->value,
            'subject_type' => FailureReport::class,
            'subject_id' => $failureReport->id,
            'causer_type' => Admin::class,
            'causer_id' => $admin->id,
            'event' => 'status_updated',
        ]);
    }
}
