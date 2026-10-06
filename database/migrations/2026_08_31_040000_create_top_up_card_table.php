<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('top_up_card', function (Blueprint $table) {
            $table->id();
            $table->string('serial_no', 32)->unique();
            $table->string('pin', 64)->unique();
            $table->integer('amount');
            $table->string('status', 16)->default('active')->index();
            $table->date('expires_at')->index();
            $table->timestamp('redeemed_at')->nullable();
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('office_id')->constrained('offices')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('batch_id')->constrained('batches')->cascadeOnUpdate()->restrictOnDelete();
            $table
                ->foreignId('ledger_transaction_id')
                ->nullable()
                ->constrained('ledger_transactions')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('top_up_card');
    }
};
