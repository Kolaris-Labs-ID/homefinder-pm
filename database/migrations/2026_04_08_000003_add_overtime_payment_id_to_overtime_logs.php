<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_logs', function (Blueprint $table) {
            $table->foreignId('overtime_payment_id')
                ->nullable()
                ->after('auto_closed')
                ->constrained('overtime_payments')
                ->nullOnDelete();

            $table->index('overtime_payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('overtime_logs', function (Blueprint $table) {
            $table->dropForeign(['overtime_payment_id']);
            $table->dropColumn('overtime_payment_id');
        });
    }
};
