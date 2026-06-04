<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->string('normalized_email')->nullable()->after('email');
            $table->string('source')->default('manual_hr')->after('cover_letter');
            $table->string('public_application_token')->nullable()->after('source');
            $table->string('application_reference_code')->nullable()->unique()->after('public_application_token');

            $table->boolean('consent_privacy_policy')->default(false)->after('application_reference_code');
            $table->boolean('consent_data_processing')->default(false)->after('consent_privacy_policy');
            $table->string('consent_policy_version')->nullable()->after('consent_data_processing');
            $table->timestamp('consent_accepted_at')->nullable()->after('consent_policy_version');

            $table->string('applied_ip', 45)->nullable()->after('consent_accepted_at');
            $table->text('applied_user_agent')->nullable()->after('applied_ip');

            $table->timestamp('retention_until')->nullable()->after('applied_user_agent');
            $table->timestamp('deletion_requested_at')->nullable()->after('retention_until');
            $table->timestamp('anonymized_at')->nullable()->after('deletion_requested_at');

            $table->string('linkedin_url')->nullable()->after('anonymized_at');
            $table->string('portfolio_url')->nullable()->after('linkedin_url');
            $table->decimal('expected_salary', 15, 2)->nullable()->after('portfolio_url');
            $table->date('available_start_date')->nullable()->after('expected_salary');

            // DB-level duplicate protection: one application per job per email.
            $table->unique(['job_posting_id', 'normalized_email']);
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropUnique(['job_posting_id', 'normalized_email']);
            $table->dropColumn([
                'normalized_email', 'source', 'public_application_token', 'application_reference_code',
                'consent_privacy_policy', 'consent_data_processing', 'consent_policy_version', 'consent_accepted_at',
                'applied_ip', 'applied_user_agent', 'retention_until', 'deletion_requested_at', 'anonymized_at',
                'linkedin_url', 'portfolio_url', 'expected_salary', 'available_start_date',
            ]);
        });
    }
};
