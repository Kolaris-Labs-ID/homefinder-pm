<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Align the database constraints with AttendanceLog's existing client-visit
 * status and source values. Without this, MySQL rejects same-day visit logs
 * while SQLite-based feature tests accept them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->enum('status', [
                'present',
                'late',
                'half_day',
                'absent',
                'leave',
                'holiday',
                'visit',
            ])->default('present')->change();

            $table->enum('source', [
                'web',
                'mobile',
                'biometric',
                'import',
                'visit',
            ])->default('web')->change();
        });
    }

    public function down(): void
    {
        if (DB::table('attendance_logs')
            ->where('status', 'visit')
            ->orWhere('source', 'visit')
            ->exists()) {
            throw new LogicException('Cannot remove attendance visit enum values while visit records exist.');
        }

        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->enum('status', [
                'present',
                'late',
                'half_day',
                'absent',
                'leave',
                'holiday',
            ])->default('present')->change();

            $table->enum('source', [
                'web',
                'mobile',
                'biometric',
                'import',
            ])->default('web')->change();
        });
    }
};
