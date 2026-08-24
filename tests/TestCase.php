<?php

namespace Tests;

use App\Models\Admin;
use App\Support\AppPermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected bool $autoGrantPermissions = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function actingAs($user, $guard = null)
    {
        if ($this->autoGrantPermissions && $user instanceof Admin) {
            RolePermissionSeeder::sync();
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            if ($user->roles()->doesntExist() && $user->permissions()->doesntExist()) {
                $user->givePermissionTo(AppPermissions::names());
            }
        }

        return parent::actingAs($user, $guard);
    }
}
