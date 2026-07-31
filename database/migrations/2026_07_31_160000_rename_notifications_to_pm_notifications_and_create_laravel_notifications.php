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
        Schema::rename('notifications', 'pm_notifications');

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('notifications');
        Schema::rename('pm_notifications', 'notifications');
    }
};
