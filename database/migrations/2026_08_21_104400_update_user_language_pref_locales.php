<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('language_pref', 'mm')->update(['language_pref' => 'my']);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY language_pref VARCHAR(8) NOT NULL DEFAULT 'my'");
        }
    }

    public function down(): void
    {
        DB::table('users')->where('language_pref', 'my')->update(['language_pref' => 'mm']);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY language_pref VARCHAR(8) NOT NULL DEFAULT 'mm'");
        }
    }
};
