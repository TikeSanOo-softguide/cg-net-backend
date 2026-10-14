<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Support\AppPermissions;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminRoleSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        Admin::query()
            ->where('username', 'Super Admin')
            ->first()
            ?->syncRoles([AppPermissions::SuperAdmin]);

        Admin::query()
            ->where('username', 'Staff Officer')
            ->first()
            ?->syncRoles([AppPermissions::StaffOfficer]);

        Admin::query()
            ->where('username', 'Support Agent')
            ->first()
            ?->syncRoles([AppPermissions::SupportAgent]);
    }
}
