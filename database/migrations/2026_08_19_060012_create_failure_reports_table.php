<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failure_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('broadband_account_id')->constrained()->cascadeOnDelete();
            $table->string('failure_type', 24)->index();
            $table->text('description');
            $table->json('photo_paths')->nullable();
            $table->string('contact_name');
            $table->string('contact_phone', 16);
            $table->string('status', 16)->default('under_review')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failure_reports');
    }
};
