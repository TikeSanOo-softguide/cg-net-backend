<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_log_index_exposes_db_event_and_log_filters(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();

        activity('customers')->causedBy($admin)->performedOn($user)->event('created')->log('customer_created');

        activity('customers')->causedBy($admin)->performedOn($user)->event('updated')->log('customer_updated');

        $this->actingAs($admin, 'web')
            ->get('/activity-logs')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('ActivityLog/Index')
                    ->has('filterOptions.event', 2)
                    ->has('filterOptions.log', 2)
                    ->where('filterOptions.event.0', 'created')
                    ->where('filterOptions.log.0', 'customer_created'),
            );
    }

    public function test_activity_log_index_filters_by_selected_event_and_log(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();

        activity('customers')->causedBy($admin)->performedOn($user)->event('created')->log('customer_created');

        activity('customers')->causedBy($admin)->performedOn($user)->event('updated')->log('customer_updated');

        $this->actingAs($admin, 'web')
            ->get('/activity-logs?event=created&log=customer_created')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->has('logs.data', 1)
                    ->where('logs.data.0.event', 'created')
                    ->where('logs.data.0.log_name', 'customer_created'),
            );
    }
}
