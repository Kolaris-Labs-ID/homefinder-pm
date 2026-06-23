<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records candidates for the ClientVisit employee_id corruption introduced by
 * `Select::make('employee_id')->relationship('employee.user', 'name')`, which
 * stored the selected USER id into employee_id instead of the EMPLOYEE id.
 *
 * Correct (API-created) rows and corrupt (admin-created) rows are
 * indistinguishable at the data level, so this migration does NOT rewrite
 * client_visits. It only populates a reversible review table. Apply confirmed
 * corrections with: php artisan client-visits:apply-employee-backfill
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_visit_employee_backfill', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_visit_id')->unique();
            $table->unsignedBigInteger('old_employee_id');
            $table->unsignedBigInteger('suggested_employee_id');
            $table->string('old_employee_name')->nullable();
            $table->string('suggested_employee_name')->nullable();
            $table->boolean('has_activity')->default(false);
            $table->boolean('applied')->default(false);
            $table->timestamps();
        });

        // suggested employee = the one whose user_id equals the stored value
        // (i.e. reinterpreting the corrupt user-id as what it should have been).
        $rows = DB::table('client_visits as cv')
            ->join('employees as suggested', 'suggested.user_id', '=', 'cv.employee_id')
            ->leftJoin('employees as stored', 'stored.id', '=', 'cv.employee_id')
            ->leftJoin('users as stored_user', 'stored_user.id', '=', 'stored.user_id')
            ->leftJoin('users as suggested_user', 'suggested_user.id', '=', 'suggested.user_id')
            ->whereColumn('suggested.id', '!=', 'cv.employee_id')
            ->select([
                'cv.id as client_visit_id',
                'cv.employee_id as old_employee_id',
                'cv.actual_check_in',
                'cv.actual_check_out',
                'cv.status',
                'suggested.id as suggested_employee_id',
                'stored_user.name as old_employee_name',
                'suggested_user.name as suggested_employee_name',
            ])
            ->get();

        $activeVisitIds = DB::table('attendance_logs')
            ->whereNotNull('client_visit_id')
            ->pluck('client_visit_id')
            ->flip();

        $now = now();
        foreach ($rows as $row) {
            $hasActivity = $row->actual_check_in !== null
                || $row->actual_check_out !== null
                || in_array($row->status, ['in_progress', 'completed'], true)
                || $activeVisitIds->has($row->client_visit_id);

            DB::table('client_visit_employee_backfill')->insert([
                'client_visit_id' => $row->client_visit_id,
                'old_employee_id' => $row->old_employee_id,
                'suggested_employee_id' => $row->suggested_employee_id,
                'old_employee_name' => $row->old_employee_name,
                'suggested_employee_name' => $row->suggested_employee_name,
                'has_activity' => $hasActivity,
                'applied' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Revert any corrections that were applied, then drop the review table.
        DB::table('client_visit_employee_backfill')
            ->where('applied', true)
            ->orderBy('id')
            ->each(function ($row) {
                DB::table('client_visits')
                    ->where('id', $row->client_visit_id)
                    ->update(['employee_id' => $row->old_employee_id]);
            });

        Schema::dropIfExists('client_visit_employee_backfill');
    }
};
