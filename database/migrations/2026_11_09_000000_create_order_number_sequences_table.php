<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_number_sequences', function (Blueprint $table): void {
            $table->date('sequence_date');
            $table->unsignedInteger('next_number')->default(1);
            $table->timestamps();

            $table->primary('sequence_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_number_sequences');
    }
};
