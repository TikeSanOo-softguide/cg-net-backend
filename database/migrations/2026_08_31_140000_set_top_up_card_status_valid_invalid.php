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

        DB::table('top_up_card')->whereIn('status', ['unused', 'active', 'valid'])->update(['status' => 'active']);
        DB::table('top_up_card')->whereIn('status', ['void', 'invalid'])->update(['status' => 'blocked']);

        $this->setStatusDefault('active');
    }

    public function down(): void
    {
        if (! Schema::hasTable('top_up_card')) {
            return;
        }

        DB::table('top_up_card')->where('status', 'active')->update(['status' => 'valid']);
        DB::table('top_up_card')->where('status', 'blocked')->update(['status' => 'void']);

        $this->setStatusDefault('active');
    }

    private function setStatusDefault(string $default): void
    {
        $safe = str_replace("'", "''", $default);
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE top_up_card MODIFY status VARCHAR(16) NOT NULL DEFAULT '{$safe}'");

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE top_up_card ALTER COLUMN status SET DEFAULT '{$safe}'");
        }
    }
};
