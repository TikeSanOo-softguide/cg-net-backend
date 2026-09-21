<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('networks', function (Blueprint $table) {
            $table->id();
            $table->string('name_en', 50)->unique();
            $table->string('name_zh', 50)->unique();
            $table->string('name_my', 50)->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['name_en', 'deleted_at']);
            $table->unique(['name_zh', 'deleted_at']);
            $table->unique(['name_my', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('networks');
    }
};
