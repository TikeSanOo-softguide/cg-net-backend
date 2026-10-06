<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('term_and_conditions', function (Blueprint $table) {
            $table->id();
            $table->string('title_en', 120);
            $table->string('title_zh', 120);
            $table->string('title_my', 120);
            $table->text('description_en');
            $table->text('description_zh');
            $table->text('description_my');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('term_and_conditions');
    }
};
