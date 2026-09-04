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
        Schema::create('change_password_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('broadband_account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('contact_name', 255)->nullable();
            $table->string('contact_phone', 255)->nullable();
            $table->string('new_wifi_name', 255)->nullable();
            $table->string('new_password', 255)->nullable();
            $table->string('status', 16)->default('pending');
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('change_password_requests');
    }
};
