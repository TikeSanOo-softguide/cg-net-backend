<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('language_pref', 'th')->update(['language_pref' => 'zh']);
    }

    public function down(): void
    {
        DB::table('users')->where('language_pref', 'zh')->update(['language_pref' => 'th']);
    }
};
