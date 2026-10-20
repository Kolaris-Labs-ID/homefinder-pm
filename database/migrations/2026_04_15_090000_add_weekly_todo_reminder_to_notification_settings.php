<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('hris_notification_settings', 'weekly_todo_reminder')) {
            Schema::table('hris_notification_settings', function (Blueprint $table) {
                $table->boolean('weekly_todo_reminder')->default(true)->after('payslip_notifications');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('hris_notification_settings', 'weekly_todo_reminder')) {
            Schema::table('hris_notification_settings', function (Blueprint $table) {
                $table->dropColumn('weekly_todo_reminder');
            });
        }
    }
};
