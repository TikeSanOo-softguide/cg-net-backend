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
            $table->string('name', 50);
            $table->text('message_en');
            $table->text('message_my');
            $table->text('message_zh');
            $table->boolean('is_start')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_flow_steps');
    }
};
