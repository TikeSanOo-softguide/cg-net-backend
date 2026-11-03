<?php

namespace Tests\Feature;

use App\Enums\ActivityLogEvent;
use App\Enums\ActivityLogName;
use App\Models\Admin;
use App\Models\Area;
use App\Models\Region;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_log_index_exposes_event_and_registered_log_filters(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();

        activity(ActivityLogName::Customers->value)->causedBy($admin)->performedOn($user)->event(ActivityLogEvent::Created->value)->log('customer_created');

        activity(ActivityLogName::Customers->value)->causedBy($admin)->performedOn($user)->event(ActivityLogEvent::Updated->value)->log('customer_updated');

        $this->actingAs($admin, 'web')
            ->get('/logs/activity')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Logs/ActivityLog/Index')
                    ->where('filterOptions.event', ActivityLogEvent::values())
                    ->where('filterOptions.log', ActivityLogName::groupedValues())
                    ->where('filterOptions.event.0', 'account_bound')
                    ->where('filterOptions.log.0.group', 'menu.customers_list')
                    ->where('filterOptions.log.0.options.0', 'customers'),
            );
    }

    public function test_activity_log_index_filters_by_selected_event_and_log(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();

        activity(ActivityLogName::Customers->value)->causedBy($admin)->performedOn($user)->event(ActivityLogEvent::Created->value)->log('customer_created');

        activity(ActivityLogName::Customers->value)->causedBy($admin)->performedOn($user)->event(ActivityLogEvent::Updated->value)->log('customer_updated');

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

        activity(ActivityLogName::Customers->value)
            ->causedBy($admin)
            ->performedOn($user)
            ->event(ActivityLogEvent::Created->value)
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

    public function test_region_state_and_area_changes_are_logged(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'web')
            ->post(route('regions.states.store'), [
                'name_en' => 'North State',
                'name_zh' => '北州',
                'name_my' => 'North State MM',
                'latitude' => 16.8,
                'longitude' => 96.2,
            ])
            ->assertRedirect(route('regions.index'));
        $state = State::query()->firstOrFail();

        $this->actingAs($admin, 'web')
            ->put(route('regions.states.update', $state), [
                'name_en' => 'Updated North State',
                'name_zh' => '更新北州',
                'name_my' => 'Updated North State MM',
                'latitude' => 16.9,
                'longitude' => 96.3,
            ])
            ->assertRedirect(route('regions.index'));

        $this->actingAs($admin, 'web')
            ->post(route('regions.regions.store'), [
                'name_en' => 'North Region',
                'name_zh' => '北區',
                'name_my' => 'North Region MM',
                'state_id' => $state->id,
                'latitude' => 16.8,
                'longitude' => 96.2,
            ])
            ->assertRedirect(route('regions.index'));
        $region = Region::query()->firstOrFail();

        $this->actingAs($admin, 'web')
            ->put(route('regions.regions.update', $region), [
                'name_en' => 'Updated North Region',
                'name_zh' => '更新北區',
                'name_my' => 'Updated North Region MM',
                'state_id' => $state->id,
                'latitude' => 16.9,
                'longitude' => 96.3,
            ])
            ->assertRedirect(route('regions.index'));

        $this->actingAs($admin, 'web')
            ->post(route('regions.areas.store'), [
                'name_en' => 'North Area',
                'name_zh' => '北區域',
                'name_my' => 'North Area MM',
                'region_id' => $region->id,
                'latitude' => 16.8,
                'longitude' => 96.2,
            ])
            ->assertRedirect(route('regions.index'));
        $area = Area::query()->firstOrFail();

        $this->actingAs($admin, 'web')
            ->put(route('regions.areas.update', $area), [
                'name_en' => 'Updated North Area',
                'name_zh' => '更新北區域',
                'name_my' => 'Updated North Area MM',
                'region_id' => $region->id,
                'latitude' => 16.9,
                'longitude' => 96.3,
            ])
            ->assertRedirect(route('regions.index'));

        $this->actingAs($admin, 'web')->delete(route('regions.areas.destroy', $area))->assertRedirect(route('regions.index'));
        $this->actingAs($admin, 'web')->delete(route('regions.regions.destroy', $region))->assertRedirect(route('regions.index'));
        $this->actingAs($admin, 'web')->delete(route('regions.states.destroy', $state))->assertRedirect(route('regions.index'));

        $this->assertSame(
            9,
            Activity::query()
                ->whereIn('log_name', [
                    ActivityLogName::State->value,
                    ActivityLogName::Region->value,
                    ActivityLogName::Area->value,
                ])
                ->where('causer_id', $admin->id)
                ->whereIn('description', [
                    'state_created',
                    'state_updated',
                    'state_deleted',
                    'region_created',
                    'region_updated',
                    'region_deleted',
                    'area_created',
                    'area_updated',
                    'area_deleted',
                ])
                ->count(),
        );
    }

    public function test_bulk_deleting_states_regions_and_areas_logs_deleted_ids_once(): void
    {
        $admin = Admin::factory()->create();
        $state = State::factory()->create();
        $region = Region::factory()->for($state)->create();
        $area = Area::factory()->for($region)->create();

        $this->actingAs($admin, 'web')->delete(route('regions.areas.bulk-destroy'), [
            'ids' => [$area->id],
        ])->assertRedirect(route('regions.index'));

        $this->actingAs($admin, 'web')->delete(route('regions.regions.bulk-destroy'), [
            'ids' => [$region->id],
        ])->assertRedirect(route('regions.index'));

        $this->actingAs($admin, 'web')->delete(route('regions.states.bulk-destroy'), [
            'ids' => [$state->id],
        ])->assertRedirect(route('regions.index'));

        foreach ([
            ['areas_bulk_deleted', ActivityLogName::Area, $area],
            ['regions_bulk_deleted', ActivityLogName::Region, $region],
            ['states_bulk_deleted', ActivityLogName::State, $state],
        ] as [$description, $logName, $model]) {
            $activity = Activity::query()
                ->where('log_name', $logName->value)
                ->where('causer_id', $admin->id)
                ->where('description', $description)
                ->sole();

            $this->assertSame('bulk_deleted', $activity->event);
            $this->assertSame([$model->id], $activity->properties->get('ids'));
        }
    }
}
