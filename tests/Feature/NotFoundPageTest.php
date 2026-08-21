<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotFoundPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_routes_render_the_inertia_404_page(): void
    {
        $this->get('/this-route-does-not-exist')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Errors/NotFound')
                ->where('auth.user', null));
    }

    public function test_authenticated_users_see_a_link_back_to_the_dashboard_context(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'web')
            ->get('/this-route-does-not-exist')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Errors/NotFound')
                ->where('auth.user.id', $admin->id)
                ->has('translations')
                ->has('locale'));
    }

    public function test_missing_models_render_the_inertia_404_page(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'web')
            ->get('/customers/999999')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Errors/NotFound')
                ->where('auth.user.id', $admin->id));
    }
}
