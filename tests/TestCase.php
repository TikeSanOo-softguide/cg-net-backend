<?php

namespace Tests;

use App\Models\Admin;
use App\Support\AppPermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected bool $autoGrantPermissions = true;

    public function createApplication()
    {
        $app = parent::createApplication();

        // RefreshDatabase wipes whatever database is configured; a cached config or container env can point it at dev data.
        if ($app['config']->get('database.default') !== 'sqlite') {
            throw new RuntimeException(
                'Tests must run on SQLite but resolved "'.$app['config']->get('database.default').'". Run `php artisan config:clear` and check phpunit.xml.'
            );
        }

        return $app;
    }

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
