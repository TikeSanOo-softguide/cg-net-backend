<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('top_up_card_sequences', function (Blueprint $table): void {
            $table->date('sequence_date');
            $table->char('agent_code', 2)->default('88');
            $table->char('batch_code', 4)->default('1101');
            $table->unsignedInteger('next_counter')->default(1001);
            $table->timestamps();

            $table->primary(['sequence_date', 'agent_code', 'batch_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('top_up_card_sequences');
    }
};
