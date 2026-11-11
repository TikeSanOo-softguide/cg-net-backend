<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('offices')) {
            return;
        }

        Schema::create('offices', function (Blueprint $table): void {
            $table->id();
            $table->string('name_en', 50);
            $table->string('name_mm', 50);
            $table->string('name_zh', 50);
            $table->char('cd', 2);
            $table->string('address_en', 255);
            $table->string('address_mm', 255);
            $table->string('address_zh', 255);
            $table->string('phone_num1', 16);
            $table->string('phone_num2', 16)->nullable();
            $table->string('phone_num3', 16)->nullable();
            $table->string('telegram_link')->nullable();
            $table->string('wechat_link')->nullable();
            $table->string('viber_link')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement(
            'CREATE UNIQUE INDEX offices_cd_active_unique
            ON offices (cd)
            WHERE deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('offices');
    }
};
