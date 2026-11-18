<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('top_up_card', function (Blueprint $table): void {
            $table->index(['office_id', 'deleted_at'], 'top_up_card_office_deleted_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('top_up_card', function (Blueprint $table): void {
            $table->dropIndex('top_up_card_office_deleted_at_index');
        });
    }
};
