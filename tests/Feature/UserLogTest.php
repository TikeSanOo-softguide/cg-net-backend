<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_log_index_exposes_the_persisted_event_column(): void
    {
        $admin = Admin::factory()->create();
        $user = User::factory()->create();
        UserLog::query()->create([
            'user_id' => $user->id,
            'event' => 'login_success',
            'metadata' => ['action' => 'login_success'],
        ]);

        $this->actingAs($admin, 'web')
            ->get('/logs/users')
            ->assertOk()
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('UserLog/index')
                    ->has('logs.data', 1)
                    ->has('filterOptions.event', 1)
                    ->where('filterOptions.event.0', 'login_success')
                    ->where('logs.data.0.event', 'login_success'),
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
