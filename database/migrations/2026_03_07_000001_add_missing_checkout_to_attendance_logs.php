<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->boolean('missing_checkout')->default(false)->after('status');
            $table->index('missing_checkout', 'attendance_logs_missing_checkout_index');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropIndex('attendance_logs_missing_checkout_index');
            $table->dropColumn('missing_checkout');
        });
    }
};
