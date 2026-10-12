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
            ->get('/logs/activity')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Logs/ActivityLog/Index')
                    ->has('filterOptions.event', 2)
                    ->has('filterOptions.log', 1)
                    ->where('filterOptions.event.0', 'created')
                    ->where('filterOptions.log.0', 'customers'),
            );
    }

    public function test_activity_log_index_filters_by_selected_event_and_log(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();

        activity('customers')->causedBy($admin)->performedOn($user)->event('created')->log('customer_created');

        activity('customers')->causedBy($admin)->performedOn($user)->event('updated')->log('customer_updated');

        $this->actingAs($admin, 'web')
            ->get('/logs/activity?event=created&log=customers')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->has('logs.data', 1)
                    ->where('logs.data.0.event', 'created')
                    ->where('logs.data.0.log_name', 'customers')
                    ->where('logs.data.0.description', 'customer_created'),
            );
    }

    public function test_activity_log_index_shows_properties_for_logs_without_old_and_new_diff(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();

        activity('customers')
            ->causedBy($admin)
            ->performedOn($user)
            ->event('created')
            ->withProperties([
                'name' => 'Alice',
                'status' => 'active',
            ])
            ->log('customer_created');

        $this->actingAs($admin, 'web')
            ->get('/logs/activity?event=created&log=customers')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->has('logs.data', 1)
                    ->where('logs.data.0.meta_data.name', 'Alice')
                    ->where('logs.data.0.meta_data.status', 'active'),
            );
    }
}
