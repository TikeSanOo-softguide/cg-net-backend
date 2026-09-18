<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_flow_option_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('option_id')
                ->constrained('chat_flow_options')
                ->cascadeOnDelete();
            $table->string('language', 10);
            $table->text('reply_text');
            $table->timestamps();
            $table->unique(['option_id', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_flow_option_replies');
    }
};
