<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 64)->unique();
            $table->string('title_en', 120);
            $table->string('title_my', 120);
            $table->string('title_zh', 120);
            $table->text('description_en');
            $table->text('description_my');
            $table->text('description_zh');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
