<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('push_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('title_en', 120);
            $table->string('title_zh', 120);
            $table->string('title_my', 120);
            $table->dateTime('scheduled_at')->index();
            $table->string('status')->default('pending')->index();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_schedules');
    }
};
