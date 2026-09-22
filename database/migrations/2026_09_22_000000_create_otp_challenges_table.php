<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('challenge_id', 64)->unique();
            $table->string('phone', 16)->index();
            $table->string('otp_hash', 128)->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('purpose', 32)->index();
            $table->timestamp('expires_at')->index();
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->timestamp('consumed_at')->nullable()->index();
            $table->timestamps();

            $table->index(['phone', 'purpose', 'consumed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_challenges');
    }
};
