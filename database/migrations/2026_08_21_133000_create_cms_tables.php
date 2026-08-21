<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('start_date')->nullable()->index();
            $table->date('end_date')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->string('image_path');
            $table->string('lang', 8)->default('en')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('lang', 8)->default('en')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['lang', 'slug']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('lang', 8)->default('en')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['lang', 'slug']);
        });

        Schema::create('news', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->longText('content');
            $table->string('image_path')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->string('lang', 8)->default('en')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['lang', 'slug']);
        });

        Schema::create('news_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained('tags')->cascadeOnDelete();
            $table->foreignId('news_id')->constrained('news')->cascadeOnDelete();
            $table->unique(['news_id', 'tag_id']);
        });

        Schema::create('gallery', function (Blueprint $table) {
            $table->id();
            $table->string('image_path');
            $table->string('label')->nullable();
            $table->string('lang', 8)->default('en')->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('contact_point');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_tags');
        Schema::dropIfExists('news');
        Schema::dropIfExists('gallery');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('categories');
    }
};
