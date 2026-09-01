<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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
            $table->date('expires_at')->nullable()->index();
            $table->timestamp('redeemed_at')->nullable();
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('valid')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('top_up_card');
    }
};
