<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failure_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('failure_report_id')->constrained()->cascadeOnDelete();
            $table->string('image_url', 500);
            $table->string('label')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failure_photos');
    }
};
