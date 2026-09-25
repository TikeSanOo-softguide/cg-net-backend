<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('top_up_card_batch_codes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('amount')->unique();
            $table->string('batch_code', 4)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('top_up_card_batch_codes');
    }
};
