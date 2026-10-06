<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->string('transaction_no', 255)->unique();
            $table->string('type', 24);
            $table->string('status', 16)->default('pending');
            $table->bigInteger('amount');
            $table->string('idempotency_key', 100)->unique();
            $table->foreignId('reversal_of')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
            $table->foreignId('related_transaction_id')
                ->nullable()
                ->constrained('ledger_transactions')
                ->restrictOnDelete();
            $table->text('note')->nullable();
            $table->string('actor_type', 32)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->index('type');
            $table->index('status');
            $table->index(['actor_type', 'actor_id']);
            $table->index('posted_at');
            $table->index(['wallet_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_transactions');
    }
};
