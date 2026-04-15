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
            $table->boolean('ticket_updates')->default(true)->after('weekly_todo_reminder');
            $table->boolean('activity_updates')->default(true)->after('ticket_updates');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hris_notification_settings', function (Blueprint $table) {
            $table->dropColumn(['ticket_updates', 'activity_updates']);
        });
    }
};
