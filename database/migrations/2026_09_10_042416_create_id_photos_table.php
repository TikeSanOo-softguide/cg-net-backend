<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('id_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installation_application_id')->constrained('installation_applications')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('image_url', 500);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('id_photos');
    }
};
