<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->foreignId('hiring_company_id')->nullable()->after('department_id')
                ->constrained('hiring_companies')->nullOnDelete();
            $table->string('slug')->nullable()->unique()->after('title');
            $table->string('public_title')->nullable()->after('slug');
            $table->text('public_description')->nullable()->after('description');
            $table->text('public_requirements')->nullable()->after('requirements');
            $table->text('public_benefits')->nullable()->after('public_requirements');
            $table->string('work_arrangement')->nullable()->after('location');
            $table->decimal('salary_min', 15, 2)->nullable()->after('public_benefits');
            $table->decimal('salary_max', 15, 2)->nullable()->after('salary_min');
            $table->boolean('salary_hidden')->default(true)->after('salary_max');
            $table->date('application_deadline')->nullable()->after('salary_hidden');
            $table->boolean('is_public')->default(false)->after('application_deadline');
            $table->timestamp('published_public_at')->nullable()->after('is_public');
            $table->timestamp('closed_public_at')->nullable()->after('published_public_at');

            $table->index(['is_public', 'status']);
            $table->index('hiring_company_id');
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hiring_company_id');
            $table->dropColumn([
                'slug', 'public_title', 'public_description', 'public_requirements',
                'public_benefits', 'work_arrangement', 'salary_min', 'salary_max',
                'salary_hidden', 'application_deadline', 'is_public',
                'published_public_at', 'closed_public_at',
            ]);
        });
    }
};
