<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            // Visit details
            $table->string('client_name');
            $table->string('client_address');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            // Visit purpose - based on company services
            $table->enum('purpose', [
                'accounting_training',
                'tax_consultation',
                'company_establishment',
                'legal_permits',
                'business_permits',
                'audit',
                'other'
            ]);
            $table->string('purpose_detail')->nullable(); // Additional detail for 'other' or specific notes

            // Timing
            $table->date('visit_date');
            $table->time('planned_start_time')->nullable();
            $table->time('planned_end_time')->nullable();
            $table->timestamp('actual_check_in')->nullable();
            $table->timestamp('actual_check_out')->nullable();
            $table->decimal('check_in_latitude', 10, 8)->nullable();
            $table->decimal('check_in_longitude', 11, 8)->nullable();
            $table->decimal('check_out_latitude', 10, 8)->nullable();
            $table->decimal('check_out_longitude', 11, 8)->nullable();

            // Notes and documentation
            $table->text('notes')->nullable();
            $table->text('visit_report')->nullable(); // Report after visit
            $table->string('photo_path')->nullable(); // Photo proof

            // Status
            $table->enum('status', ['planned', 'in_progress', 'completed', 'cancelled'])->default('planned');
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['employee_id', 'visit_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_visits');
    }
};
