<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicant_screening_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->constrained('job_postings')->cascadeOnDelete();
            $table->string('group', 16); // must_have | nice_to_have | fit | knockout
            $table->string('label');
            $table->json('keywords')->nullable();
            $table->json('synonyms')->nullable();
            $table->unsignedInteger('weight')->default(1);
            $table->boolean('missing_means_failed')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            // = lower(trim(label)) while alive, NULL when soft-deleted. NULL-distinct in
            // unique indexes (MySQL + SQLite) lets a label be re-created after soft delete.
            $table->string('label_active_key')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['job_posting_id', 'group', 'label_active_key'], 'criteria_active_label_unique');
            $table->index(['job_posting_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_screening_criteria');
    }
};
