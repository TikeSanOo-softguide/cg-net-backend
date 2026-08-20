<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Support\MenuPages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MenuPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_menu_pages(): void
    {
        $this->get('/customers')->assertRedirect('/login');
    }

    public function test_authenticated_admins_see_empty_menu_pages_with_title_keys(): void
    {
        $admin = Admin::factory()->create();

        foreach (MenuPages::all() as $page) {
            $this->actingAs($admin, 'web')
                ->get($page['path'])
                ->assertOk()
                ->assertInertia(fn (Assert $inertia) => $inertia
                    ->component('Placeholder/Index')
                    ->where('titleKey', $page['titleKey']));
        }
    }
}
