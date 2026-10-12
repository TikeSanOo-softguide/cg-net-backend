<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admin_notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_notification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['admin_notification_id', 'admin_id']);
            $table->index(['admin_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notification_recipients');
    }
};
