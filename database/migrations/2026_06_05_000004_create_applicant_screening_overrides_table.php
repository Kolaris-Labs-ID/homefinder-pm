<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicant_screening_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->foreignId('job_posting_id')->constrained('job_postings')->cascadeOnDelete();
            $table->string('overridden_recommendation', 24);
            $table->text('override_note');
            $table->boolean('is_active')->default(true);
            // 1 while active, NULL when cleared. NULL-distinct uniqueness => at most one
            // active override per (applicant, job), unlimited cleared rows.
            $table->unsignedTinyInteger('active_marker')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cleared_at')->nullable();
            $table->text('clear_note')->nullable();
            $table->timestamps();

            $table->unique(['applicant_id', 'job_posting_id', 'active_marker'], 'override_active_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_screening_overrides');
    }
};
