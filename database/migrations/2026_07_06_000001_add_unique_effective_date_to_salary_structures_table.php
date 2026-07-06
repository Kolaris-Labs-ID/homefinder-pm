<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enforce one salary structure per (employee, effective_date). Duplicate
     * effective dates made "which structure is active" nondeterministic, which
     * is unacceptable for payroll. Replaces the non-unique composite index.
     *
     * Fails fast with a clear message (instead of a raw unique-index
     * QueryException) if production already contains duplicate
     * (employee_id, effective_date) rows — run
     * `php artisan hris:salary-structures:report-duplicates` to find and
     * resolve them manually first.
     */
    public function up(): void
    {
        $duplicates = app(\App\Services\Hris\DuplicateSalaryStructureFinder::class)->find();

        if ($duplicates->isNotEmpty()) {
            throw new \RuntimeException(
                "Cannot add unique(employee_id, effective_date): {$duplicates->count()} duplicate combination(s) exist. ".
                'Run `php artisan hris:salary-structures:report-duplicates` and resolve them manually first.'
            );
        }

        Schema::table('salary_structures', function (Blueprint $table) {
            $table->dropIndex(['employee_id', 'effective_date']);
            $table->unique(['employee_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::table('salary_structures', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'effective_date']);
            $table->index(['employee_id', 'effective_date']);
        });
    }
};
