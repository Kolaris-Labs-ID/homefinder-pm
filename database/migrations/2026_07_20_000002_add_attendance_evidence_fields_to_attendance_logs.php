<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dateTime('check_in_captured_at')->nullable()->after('check_in_photo_path');
            $table->string('check_in_client_event_id', 64)->nullable()->after('check_in_captured_at');
            $table->string('check_in_source_platform', 20)->nullable()->after('check_in_client_event_id');
            $table->string('check_in_location_provider', 30)->nullable()->after('check_in_source_platform');
            $table->decimal('check_in_accuracy_meters', 8, 2)->nullable()->after('check_in_location_provider');

            $table->dateTime('check_out_captured_at')->nullable()->after('check_out_photo_path');
            $table->string('check_out_client_event_id', 64)->nullable()->after('check_out_captured_at');
            $table->string('check_out_source_platform', 20)->nullable()->after('check_out_client_event_id');
            $table->string('check_out_location_provider', 30)->nullable()->after('check_out_source_platform');
            $table->decimal('check_out_accuracy_meters', 8, 2)->nullable()->after('check_out_location_provider');

            $table->unique(
                ['employee_id', 'check_in_client_event_id'],
                'attendance_logs_employee_check_in_event_unique'
            );
            $table->unique(
                ['employee_id', 'check_out_client_event_id'],
                'attendance_logs_employee_check_out_event_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropUnique('attendance_logs_employee_check_in_event_unique');
            $table->dropUnique('attendance_logs_employee_check_out_event_unique');

            $table->dropColumn([
                'check_in_captured_at',
                'check_in_client_event_id',
                'check_in_source_platform',
                'check_in_location_provider',
                'check_in_accuracy_meters',
                'check_out_captured_at',
                'check_out_client_event_id',
                'check_out_source_platform',
                'check_out_location_provider',
                'check_out_accuracy_meters',
            ]);
        });
    }
};
