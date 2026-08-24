<?php

namespace Database\Seeders;

use App\Support\AppPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        self::sync();
    }

    public static function sync(): void
    {
        $guard = 'web';

        foreach (AppPermissions::names() as $name) {
            Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => $guard,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = Permission::query()->where('guard_name', $guard)->whereIn('name', AppPermissions::names())->get();

        $super = Role::query()->firstOrCreate(['name' => AppPermissions::SuperAdmin, 'guard_name' => $guard]);
        $super->syncPermissions($all);

        $staff = Role::query()->firstOrCreate(['name' => AppPermissions::StaffOfficer, 'guard_name' => $guard]);
        $staff->syncPermissions($all->filter(function (Permission $permission): bool {
            return in_array(explode('.', $permission->name)[0], [
                'dashboard',
                'customers',
                'cpe',
                'packages',
                'billing',
                'vouchers',
                'regions',
                'cms',
            ], true);
        }));

        $support = Role::query()->firstOrCreate(['name' => AppPermissions::SupportAgent, 'guard_name' => $guard]);
        $support->syncPermissions($all->filter(function (Permission $permission): bool {
            $module = explode('.', $permission->name)[0];

            if (in_array($module, ['dashboard', 'support', 'service-requests', 'notifications'], true)) {
                return true;
            }

            return $permission->name === 'customers.view';
        }));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
