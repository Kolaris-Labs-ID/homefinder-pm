<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date');
            $table->enum('mode', ['office', 'wfh'])->default('office');

            // Check-in fields
            $table->datetime('check_in_time')->nullable();
            $table->decimal('check_in_latitude', 10, 8)->nullable();
            $table->decimal('check_in_longitude', 11, 8)->nullable();
            $table->string('check_in_address')->nullable();
            $table->string('check_in_photo_path')->nullable();

            // Check-out fields
            $table->datetime('check_out_time')->nullable();
            $table->decimal('check_out_latitude', 10, 8)->nullable();
            $table->decimal('check_out_longitude', 11, 8)->nullable();
            $table->string('check_out_address')->nullable();
            $table->string('check_out_photo_path')->nullable();

            // Source and status
            $table->enum('source', ['web', 'mobile', 'biometric', 'import'])->default('web');
            $table->enum('status', ['present', 'late', 'half_day', 'absent', 'leave', 'holiday'])->default('present');

            // Additional data
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes for query performance
            $table->index('employee_id');
            $table->index('date');
            $table->index('status');
            $table->index(['employee_id', 'date']);
            $table->unique(['employee_id', 'date']); // One attendance record per employee per day
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
