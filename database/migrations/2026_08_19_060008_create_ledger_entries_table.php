<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_transaction_id')->constrained('ledger_transactions')->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->foreignId('wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->unsignedBigInteger('debit')->default(0);
            $table->unsignedBigInteger('credit')->default(0);
            $table->unsignedSmallInteger('line_no');
            $table->bigInteger('balance_before')->nullable();
            $table->bigInteger('balance_after')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['ledger_transaction_id', 'line_no']);
            $table->index(['ledger_account_id', 'id']);
            $table->index(['wallet_id', 'id']);
            $table->index(['ledger_transaction_id', 'wallet_id', 'credit', 'debit'], 'ledger_entries_tx_wallet_direction_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
