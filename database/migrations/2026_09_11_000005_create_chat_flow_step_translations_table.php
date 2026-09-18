<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_flow_step_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('step_id')
                ->constrained('chat_flow_steps')
                ->cascadeOnDelete();
            $table->string('language', 10);
            $table->text('message');
            $table->timestamps();
            $table->unique(['step_id', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_flow_step_translations');
    }
};
