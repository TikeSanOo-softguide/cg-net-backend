<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::whenTableDoesntHaveIndex('device_tokens', ['user_id'], function (Blueprint $table): void {
            $table->index('user_id', 'device_tokens_user_id_index');
        });
    }

    public function down(): void
    {
        Schema::whenTableHasIndex('device_tokens', 'device_tokens_user_id_index', function (Blueprint $table): void {
            $table->dropIndex('device_tokens_user_id_index');
        });
    }
};
