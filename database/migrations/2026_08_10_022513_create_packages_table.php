<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_id')->constrained('networks')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('speed_id')->constrained('speeds')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnUpdate()->restrictOnDelete();
            $table->integer('price');
            $table->string('image_url', 500)->nullable();
            $table->integer('installation_fee');
            $table->boolean('includes_free_iptv')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('recommended')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['network_id', 'speed_id', 'term_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
