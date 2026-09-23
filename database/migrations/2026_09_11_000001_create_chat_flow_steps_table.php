<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_flow_steps', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('message_en');
            $table->text('message_my');
            $table->text('message_zh');
            $table->boolean('is_start')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_flow_steps');
    }
};
