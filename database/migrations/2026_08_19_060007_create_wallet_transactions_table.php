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
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('transaction_no', 255)->unique();
            $table->string('type', 24);
            $table->string('status', 16)->default('pending');
            $table->integer('amount');
            $table->string('idempotency_key', 100)->unique();
            $table->foreignId('reversal_of')->nullable()->constrained('wallet_transactions')->nullOnDelete();
            $table->string('actor_type', 32)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index('type');
            $table->index('status');
            $table->index(['actor_type', 'actor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropForeign(['reversal_of']);
        });

        Schema::dropIfExists('wallet_transactions');
    }
};
