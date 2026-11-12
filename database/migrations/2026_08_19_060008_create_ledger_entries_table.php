<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_transaction_id')->constrained('ledger_transactions')->restrictOnDelete();
            $table->foreignId('ledger_account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->foreignId('wallet_id')->nullable()->constrained('wallets')->restrictOnDelete();
            $table->decimal('debit', 12, 2)->default(0);
            $table->decimal('credit', 12, 2)->default(0);
            $table->unsignedSmallInteger('line_no');
            $table->decimal('balance_before', 12, 2)->nullable();
            $table->decimal('balance_after', 12, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['ledger_transaction_id', 'line_no']);
            $table->index(['ledger_account_id', 'id']);
            $table->index(['ledger_account_id', 'debit', 'credit'], 'ledger_entries_account_amount_index');
            $table->index(['wallet_id', 'id']);
            $table->index(
                ['ledger_transaction_id', 'wallet_id', 'credit', 'debit'],
                'ledger_entries_tx_wallet_direction_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
