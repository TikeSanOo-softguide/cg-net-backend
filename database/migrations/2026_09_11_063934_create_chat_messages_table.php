<?php

use App\Enums\ChatMessageType;
use App\Enums\ChatSenderType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->enum('sender_type', array_column(ChatSenderType::cases(), 'value'));
            $table->enum(
                'message_type',
                array_column(ChatMessageType::cases(), 'value')
            )->default(ChatMessageType::Text->value);
            $table->text('message')->nullable();
            $table->string('attachment_path')->nullable();
            $table->foreignId('option_id')
                ->nullable()
                ->constrained('chat_flow_options')
                ->nullOnDelete();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
