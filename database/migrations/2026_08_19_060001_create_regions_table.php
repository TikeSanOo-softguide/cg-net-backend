<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_mm');
            $table->foreignId('parent_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'name_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
