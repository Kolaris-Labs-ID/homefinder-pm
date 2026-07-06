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
     * NOTE: if production already contains duplicate (employee_id, effective_date)
     * rows, they must be de-duplicated before this migration can apply.
     */
    public function up(): void
    {
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
