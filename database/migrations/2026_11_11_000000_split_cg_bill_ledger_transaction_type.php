<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ledger_transactions')
            ->where('type', 'ftth_bill')
            ->whereIn('id', function ($query): void {
                $query->select('ledger_transaction_id')
                    ->from('bill_payments')
                    ->where('service_type', 'CG');
            })
            ->update(['type' => 'cg_bill']);
    }

    public function down(): void
    {
        DB::table('ledger_transactions')
            ->where('type', 'cg_bill')
            ->update(['type' => 'ftth_bill']);
    }
};
