<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_authenticate_and_are_redirected_to_dashboard(): void
    {
        $admin = Admin::factory()->create([
            'email' => 'admin@cg-net.test',
        ]);

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($admin, 'web');
    }

    public function test_locale_can_be_switched_via_session(): void
    {
        $this->from('/login')
            ->post('/locale/mm')
            ->assertRedirect('/login');

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'mm')
                ->where('translations', fn ($translations) => ($translations['auth.sign_in'] ?? null) === 'ဝင်ရောက်ရန်'));
    }
}
