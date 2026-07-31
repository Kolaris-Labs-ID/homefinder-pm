<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The custom PM notification table was squatting Laravel's reserved
 * `notifications` name with an incompatible schema, which broke every
 * sendToDatabase() call and blocked the browser-push plugin. Move it to
 * `pm_notifications` and give the framework its standard table back.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Each guard makes an interrupted run resumable: MySQL DDL
        // auto-commits, so rename and create cannot share a transaction.
        if (Schema::hasTable('notifications') && ! Schema::hasTable('pm_notifications')) {
            Schema::rename('notifications', 'pm_notifications');
        }

        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Archive rather than drop: native notifications written after the
        // up-migration have no pm_notifications original to fall back to.
        Schema::rename('notifications', 'notifications_rolled_back_' . now()->format('YmdHis'));
        Schema::rename('pm_notifications', 'notifications');
    }
};
