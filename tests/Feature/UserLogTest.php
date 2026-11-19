<?php

namespace Tests\Feature;

use App\Enums\UserLogEvent;
use App\Models\Admin;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_log_index_exposes_predefined_event_options(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();
        UserLog::query()->create([
            'user_id' => $user->id,
            'event' => 'login_success',
            'metadata' => ['action' => 'login_success', 'device_name' => 'Samsung SM-A155F'],
        ]);

        $this->actingAs($admin, 'web')
            ->get('/logs/users')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Logs/UserLog/index')
                    ->has('logs.data', 1)
                    ->where('filterOptions.event', UserLogEvent::values())
                    ->where('logs.data.0.event', 'login_success')
                    ->where('logs.data.0.metadata.device_name', 'Samsung SM-A155F'),
            );
    }

    public function test_user_log_index_filters_by_selected_event(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();

        UserLog::query()->create(['user_id' => $user->id, 'event' => 'login_success']);
        UserLog::query()->create(['user_id' => $user->id, 'event' => 'logout']);

        $this->actingAs($admin, 'web')
            ->get('/logs/users?event=login_success')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->has('logs.data', 1)
                    ->where('logs.data.0.event', 'login_success')
                    ->where('filters.event', 'login_success'),
            );
    }
}
