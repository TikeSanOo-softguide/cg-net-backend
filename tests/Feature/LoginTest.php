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
            'username' => 'admin',
        ]);

        $response = $this->post('/login', [
            'username' => $admin->username,
            'password' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($admin, 'web');
    }

    public function test_invalid_credentials_are_shown_on_the_login_form(): void
    {
        Admin::factory()->create([
            'username' => 'admin',
        ]);

        $this->from('/login')
            ->post('/login', [
                'username' => 'admin',
                'password' => 'wrong-password',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors([
                'username' => 'These credentials do not match our records.',
            ]);
    }

    public function test_inactive_admins_cannot_authenticate(): void
    {
        Admin::factory()->inactive()->create([
            'username' => 'admin',
        ]);

        $this->from('/login')
            ->post('/login', [
                'username' => 'admin',
                'password' => 'password',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors([
                'username' => 'This account is inactive and cannot sign in.',
            ]);

        $this->assertGuest('web');
    }

    public function test_inactive_admins_are_logged_out_of_the_app(): void
    {
        $admin = Admin::factory()->inactive()->create();

        $this->actingAs($admin, 'web')
            ->get('/dashboard')
            ->assertRedirect('/login')
            ->assertSessionHasErrors([
                'username' => 'This account is inactive and cannot sign in.',
            ]);

        $this->assertGuest('web');
    }

    public function test_english_nested_translations_are_shared(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'en')
                ->where('translations.menu.dashboard', 'Dashboard')
                ->where('translations.auth.sign_in', 'Sign in'));
    }

    public function test_locale_can_be_switched_via_session(): void
    {
        $this->from('/login')
            ->post('/locale/my')
            ->assertRedirect('/login');

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'my')
                ->where('translations.auth.sign_in', 'ဝင်ရောက်ရန်')
                ->where('translations.menu.dashboard', 'ဒက်ရှ်ဘုတ်'));
    }

    public function test_chinese_locale_can_be_switched_via_session(): void
    {
        $this->from('/login')
            ->post('/locale/zh')
            ->assertRedirect('/login');

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'zh')
                ->where('translations.auth.sign_in', '登录')
                ->where('translations.menu.dashboard', '仪表盘'));
    }
}
