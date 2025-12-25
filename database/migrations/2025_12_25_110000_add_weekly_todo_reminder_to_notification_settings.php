<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hris_notification_settings', function (Blueprint $table) {
            $table->boolean('weekly_todo_reminder')->default(true)->after('payslip_notifications');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hris_notification_settings', function (Blueprint $table) {
            $table->dropColumn('weekly_todo_reminder');
        });
    }
};
