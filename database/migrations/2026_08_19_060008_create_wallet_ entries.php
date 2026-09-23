<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wallet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_transaction_id')->constrained('wallet_transactions')->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->integer('amount');
            $table->integer('balance_before');
            $table->integer('balance_after');
            $table->string('type', 24);
            $table->timestamp('created_at')->useCurrent();
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_entries');
    }
};
