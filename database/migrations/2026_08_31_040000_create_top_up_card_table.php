<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('top_up_card')) {
            return;
        }

        Schema::create('top_up_card', function (Blueprint $table) {
            $table->id();
            $table->string('serial_no', 32)->unique();
            $table->string('pin', 64);
            $table->decimal('amount', 10, 2);
            $table->string('status', 16)->default('active')->index();
            $table->date('expires_at')->index();
            $table->timestamp('redeemed_at')->nullable();
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('agents')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('batches')->cascadeOnUpdate()->nullOnDelete();
            $table
                ->foreignId('wallet_transaction_id')
                ->nullable()
                ->constrained('wallet_transactions')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('top_up_card');
    }
};
