<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicant_screening_results', function (Blueprint $table) {
            $table->id();
            $table->char('screening_run_id', 26)->unique(); // ULID; retry-vs-re-run key
            $table->foreignId('applicant_id')->constrained('applicants')->cascadeOnDelete();
            $table->foreignId('job_posting_id')->constrained('job_postings')->cascadeOnDelete();
            $table->foreignId('hiring_company_id')->nullable()->constrained('hiring_companies')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('parser_status', 16); // not_started | pending | parsed | failed
            $table->string('parser_failure_reason')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->string('recommendation', 24); // not_screened | needs_manual_review | low_match | review | strong_match
            $table->json('flags');
            $table->json('matched_criteria');
            $table->json('missing_criteria');
            $table->json('fit_outcomes');
            $table->json('raw_scoring_breakdown');
            $table->json('criteria_snapshot');
            $table->char('criteria_snapshot_hash', 64);
            $table->string('parser_version', 16)->nullable();
            $table->string('matcher_version', 16)->nullable();
            $table->string('screening_version', 16)->nullable();
            $table->string('synonym_dictionary_version', 16)->nullable();
            $table->date('screening_reference_date');
            $table->text('parsed_summary')->nullable();
            $table->json('redacted_fields')->nullable();
            $table->foreignId('screened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('screened_at')->nullable();
            $table->timestamps();

            $table->unique(['applicant_id', 'job_posting_id', 'version'], 'result_applicant_job_version_unique');
            $table->index(['applicant_id', 'job_posting_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_screening_results');
    }
};
