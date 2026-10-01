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
        Schema::create('bill_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ledger_transaction_id')->unique()->constrained('ledger_transactions')->cascadeOnDelete();
            $table->string('broadband_account_number', 32)->nullable()->index();
            $table->string('status', 16)->default('pending');
            $table->string('external_bill_ref', 100)->nullable();
            $table->string('external_payment_ref', 100)->nullable();
            $table->json('external_response')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('confirmed_at')->nullable();
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_payments');
    }
};
