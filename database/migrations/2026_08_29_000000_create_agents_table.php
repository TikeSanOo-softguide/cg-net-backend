<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agents')) {
            return;
        }

        Schema::create('agents', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 50);
            $table->char('cd', 2)->unique();
            $table->string('address', 255);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};