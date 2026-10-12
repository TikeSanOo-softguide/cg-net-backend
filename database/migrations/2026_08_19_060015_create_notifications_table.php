<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 24)->index();
            $table->nullableMorphs('templateable');
            $table->json('template_data')->nullable();
            $table->boolean('is_read')->default(false)->index();
            $table->timestamp('sent_at')->nullable()->index();
            $table->string('action_type', 64)->nullable();
            $table->string('action_id', 100)->nullable();
            $table->unique(['user_id', 'action_type', 'action_id'], 'notifications_action_unique');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
