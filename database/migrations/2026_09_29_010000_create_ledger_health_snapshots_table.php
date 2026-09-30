<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ledger_health_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('status', 16);
            $table->json('results')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_health_snapshots');
    }
};
