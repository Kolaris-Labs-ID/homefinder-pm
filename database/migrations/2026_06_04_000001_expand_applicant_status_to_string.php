<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Widen `status` from a fixed enum to a string. The canonical state machine
    // (applied → screening → shortlisted → interview_scheduled → interviewed →
    // offered → hired, plus rejected/withdrawn) is enforced by RecruitmentService,
    // not a DB-level enum, so new stages can be added without driver-specific churn.
    public function up(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->string('status')->default('applied')->change();
        });
    }

    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->enum('status', ['applied', 'shortlisted', 'interviewed', 'offered', 'hired', 'rejected'])
                ->default('applied')
                ->change();
        });
    }
};
