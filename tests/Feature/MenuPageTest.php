<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_menu_pages(): void
    {
        $this->get('/customers')->assertRedirect('/login');
    }
}
