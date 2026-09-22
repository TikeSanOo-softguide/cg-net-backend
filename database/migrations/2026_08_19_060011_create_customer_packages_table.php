<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained()->restrictOnDelete();
            $table->foreignId('package_order_id')->nullable()->constrained('package_orders')->nullOnDelete();
            $table->foreignId('broadband_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->date('start_date');
            $table->date('expiry_date')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'status']);
            $table->index(['package_id', 'status']);
            $table->index('broadband_account_id');
            $table->index('expired_at');
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_packages');
    }
};
