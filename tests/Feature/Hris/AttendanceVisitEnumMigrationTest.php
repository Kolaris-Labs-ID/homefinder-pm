<?php

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;

/**
 * SOURCE-TEXT GUARD, NOT BEHAVIOURAL COVERAGE: SQLite (used by this test
 * suite) does not enforce column-level enum constraints, so it cannot prove
 * that MySQL actually accepts/rejects these values — it only proves the
 * production migration file's enum definition lists them. Genuine
 * behavioural coverage that 'visit' round-trips through the app lives in
 * the second test below and end-to-end in AttendanceVisitMarkerCheckInTest
 * and AttendanceVisitStatusCheckoutTest.
 */
it('source-text guard: ships a production schema migration for both client visit attendance enum values', function () {
    $migrationPath = database_path('migrations/2026_07_20_000003_add_visit_values_to_attendance_logs_enums.php');

    expect(is_file($migrationPath))->toBeTrue();

    $migrationSource = file_get_contents($migrationPath);

    foreach ([
        'status' => AttendanceLog::STATUS_VISIT,
        'source' => AttendanceLog::SOURCE_VISIT,
    ] as $column => $visitValue) {
        preg_match(
            "/enum\\('{$column}',\\s*\\[(.*?)\\]\\)/s",
            $migrationSource,
            $matches,
        );

        expect($matches[1] ?? null)
            ->not->toBeNull("The {$column} enum migration definition is missing.")
            ->toContain("'{$visitValue}'");
    }
});

it('persists and reloads both client-visit values through the model and its declared constant lists', function () {
    $log = AttendanceLog::factory()->create([
        'employee_id' => Employee::factory(),
        'status' => AttendanceLog::STATUS_VISIT,
        'source' => AttendanceLog::SOURCE_VISIT,
    ]);

    expect($log->fresh()->status)->toBe(AttendanceLog::STATUS_VISIT)
        ->and($log->fresh()->source)->toBe(AttendanceLog::SOURCE_VISIT)
        ->and(AttendanceLog::getStatuses())->toContain(AttendanceLog::STATUS_VISIT)
        ->and(AttendanceLog::getSources())->toContain(AttendanceLog::SOURCE_VISIT);
});
