<?php

namespace Database\Seeders;

use App\Enums\AdminStatus;
use App\Models\Admin;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        echo "Admin seeder started\n";

        collect([
            ['username' => 'Super Admin'],
            ['username' => 'Staff Officer'],
            ['username' => 'Support Agent'],
        ])->each(function (array $admin): void {
            $legacy = [
                'Super Admin' => 'admin',
                'Staff Officer' => 'staff',
                'Support Agent' => 'support',
            ][$admin['username']];

            $existing = Admin::query()
                ->whereIn('username', [$admin['username'], $legacy])
                ->first();

            if ($existing) {
                $existing->update(['username' => $admin['username']]);

                return;
            }

            Admin::create([
                ...$admin,
                'password' => 'password',
                'status' => AdminStatus::Active,
            ]);
        });
    }
}
