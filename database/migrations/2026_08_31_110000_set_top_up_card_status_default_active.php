<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('top_up_card')) {
            return;
        }

        DB::table('top_up_card')->where('status', 'unused')->update(['status' => 'active']);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE top_up_card MODIFY status VARCHAR(16) NOT NULL DEFAULT 'active'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('top_up_card')) {
            return;
        }

        DB::table('top_up_card')->where('status', 'active')->update(['status' => 'unused']);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE top_up_card MODIFY status VARCHAR(16) NOT NULL DEFAULT 'unused'");
        }
    }
};
