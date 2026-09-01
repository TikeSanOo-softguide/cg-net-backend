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

        DB::table('top_up_card')->whereIn('status', ['unused', 'active'])->update(['status' => 'valid']);
        DB::table('top_up_card')->whereIn('status', ['expired', 'void'])->update(['status' => 'invalid']);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE top_up_card MODIFY status VARCHAR(16) NOT NULL DEFAULT 'valid'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('top_up_card')) {
            return;
        }

        DB::table('top_up_card')->where('status', 'valid')->update(['status' => 'active']);
        DB::table('top_up_card')->where('status', 'invalid')->update(['status' => 'void']);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE top_up_card MODIFY status VARCHAR(16) NOT NULL DEFAULT 'active'");
        }
    }
};
