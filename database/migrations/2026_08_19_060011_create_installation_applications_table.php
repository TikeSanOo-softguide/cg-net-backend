<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('installation_applications', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30);
            $table->string('service_type', 8)->nullable();
            $table->string('ticket_number', 32)->nullable()->unique();
            $table->json('package_detail');
            $table->text('address');
            $table->string('phone');
            $table->text('note')->nullable();
            $table->string('status', 16);
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_applications');
    }
};
