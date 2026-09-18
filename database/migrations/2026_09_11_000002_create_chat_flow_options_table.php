<?php

use App\Enums\ChatFlowOptionAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_flow_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('step_id')
                ->constrained('chat_flow_steps')
                ->cascadeOnDelete();
            $table->enum('action', array_column(ChatFlowOptionAction::cases(), 'value'));
            $table->foreignId('next_step_id')
                ->nullable()
                ->constrained('chat_flow_steps')
                ->nullOnDelete();
            $table->text('url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_flow_options');
    }
};
