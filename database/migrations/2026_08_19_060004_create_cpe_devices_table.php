<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cpe_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadband_account_id')->constrained()->cascadeOnDelete();
            $table->string('cpe_identifier')->index();
            $table->string('ssid');
            $table->text('wifi_password');
            $table->string('connection_status', 16)->default('good')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpe_devices');
    }
};
