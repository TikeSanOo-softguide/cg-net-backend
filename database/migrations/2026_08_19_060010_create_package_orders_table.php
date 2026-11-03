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
        Schema::create('package_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('package_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('ledger_transaction_id')->nullable()->constrained('ledger_transactions')->restrictOnDelete();
            $table->string('order_no')->nullable()->unique();
            $table->string('account', 8)->nullable()->unique();
            $table->text('activation_password')->nullable();
            $table->string('product_id')->nullable();
            $table->decimal('product_price', 12, 2)->nullable();
            $table->integer('activation_result_code')->nullable();
            $table->string('failure_reason')->nullable();
            $table->string('status', 32)->default('processing');
            $table->json('snapshot')->nullable();
            $table->json('external_response')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->index('status');
            $table->unique('ledger_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_orders');
    }
};
