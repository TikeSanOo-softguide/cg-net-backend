<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_orders', function (Blueprint $table): void {
            $table->foreignId('package_id')->nullable()->change();
            $table->string('order_no')->nullable()->unique();
            $table->string('account', 8)->nullable()->unique();
            $table->text('activation_password')->nullable();
            $table->string('product_id')->nullable();
            $table->decimal('product_price', 12, 2)->nullable();
            $table->integer('activation_result_code')->nullable();
            $table->string('failure_reason')->nullable();
            $table->string('status', 32)->default('processing')->change();
            $table->unique('ledger_transaction_id');
        });

        Schema::table('customer_packages', function (Blueprint $table): void {
            $table->foreignId('package_id')->nullable()->change();
            $table->text('password')->nullable()->change();
            $table->unique('package_order_id');
        });

        DB::table('customer_packages')
            ->whereNotNull('password')
            ->orderBy('id')
            ->select(['id', 'password'])
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('customer_packages')
                        ->where('id', $row->id)
                        ->update(['password' => Crypt::encryptString((string) $row->password)]);
                }
            });
    }

    public function down(): void
    {
        if (
            DB::table('package_orders')->whereNull('package_id')->exists()
            || DB::table('customer_packages')->whereNull('package_id')->exists()
        ) {
            throw new RuntimeException('Cannot reverse provider package orders while package references are null.');
        }

        DB::table('customer_packages')
            ->whereNotNull('password')
            ->orderBy('id')
            ->select(['id', 'password'])
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('customer_packages')
                        ->where('id', $row->id)
                        ->update(['password' => Crypt::decryptString((string) $row->password)]);
                }
            });

        Schema::table('customer_packages', function (Blueprint $table): void {
            $table->dropUnique(['package_order_id']);
            $table->string('password')->nullable()->change();
        });

        Schema::table('package_orders', function (Blueprint $table): void {
            $table->dropUnique(['ledger_transaction_id']);
            $table->dropUnique(['order_no']);
            $table->dropUnique(['account']);
            $table->string('status', 16)->default('pending')->change();
            $table->dropColumn([
                'order_no',
                'account',
                'activation_password',
                'product_id',
                'product_price',
                'activation_result_code',
                'failure_reason',
            ]);
        });

        Schema::table('package_orders', function (Blueprint $table): void {
            $table->foreignId('package_id')->nullable(false)->change();
        });

        Schema::table('customer_packages', function (Blueprint $table): void {
            $table->foreignId('package_id')->nullable(false)->change();
        });
    }
};
