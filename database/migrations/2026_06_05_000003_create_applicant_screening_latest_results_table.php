<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mutable pointer to the current immutable result for an (applicant, job) pair.
        // Only this row moves when a newer result is finalized; result rows never change.
        Schema::create('applicant_screening_latest_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->foreignId('job_posting_id')->constrained('job_postings')->cascadeOnDelete();
            $table->foreignId('latest_result_id')->constrained('applicant_screening_results')->cascadeOnDelete();
            $table->unsignedInteger('latest_version');
            $table->timestamp('updated_at')->nullable();

            $table->unique(['applicant_id', 'job_posting_id'], 'latest_applicant_job_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_screening_latest_results');
    }
};
