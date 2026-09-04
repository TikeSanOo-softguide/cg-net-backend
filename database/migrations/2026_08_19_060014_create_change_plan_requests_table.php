<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_plan_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('broadband_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('current_package_id')->constrained('packages')->restrictOnDelete();
            $table->foreignId('new_package_id')->constrained('packages')->restrictOnDelete();
            $table->date('preferred_date');
            $table->string('contact_name', 255);
            $table->string('contact_phone', 16);
            $table->text('note')->nullable();
            $table->string('status', 16)->default('under_review')->index();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_plan_requests');
    }
};
