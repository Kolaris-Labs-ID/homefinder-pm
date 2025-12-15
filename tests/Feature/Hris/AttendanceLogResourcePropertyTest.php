<?php

/**
 * Property-Based Tests for AttendanceLogResource HR Filters
 *
 * These tests verify that HR filters in the AttendanceLogResource
 * return only records matching ALL specified criteria.
 */

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

beforeEach(function () {
    // Clean up before each test
    AttendanceLog::query()->delete();
});

/**
 * **Feature: hris-phase2-attendance, Property 13: HR filter accuracy**
 *
 * For any filter criteria (employee, date range, status) applied by an HR administrator,
 * the returned AttendanceLogs SHALL contain only records matching ALL specified criteria.
 *
 * **Validates: Requirements 3.2**
 */
test('Property 13: HR filter accuracy - employee filter returns only matching records', function () {
    // Create multiple employees
    $employees = Employee::factory()->count(5)->create();

    // Run 5 iterations
    for ($i = 0; $i < 5; $i++) {
        // Create attendance logs for all employees
        foreach ($employees as $employee) {
            AttendanceLog::factory()->create([
                'employee_id' => $employee->id,
                'date' => Carbon::today()->subDays($i),
                'check_in_time' => Carbon::today()->subDays($i)->setTime(8, 0, 0),
            ]);
        }

        // Select a random employee to filter by
        $targetEmployee = $employees->random();

        // Apply employee filter using the model's scope (same as resource filter)
        $filteredLogs = AttendanceLog::query()
            ->forEmployee($targetEmployee->id)
            ->get();

        // Property: All returned logs must belong to the filtered employee
        foreach ($filteredLogs as $log) {
            expect($log->employee_id)->toBe($targetEmployee->id);
        }

        // Property: No logs from other employees should be returned
        $otherEmployeeIds = $employees->pluck('id')->reject(fn ($id) => $id === $targetEmployee->id);
        foreach ($filteredLogs as $log) {
            expect($otherEmployeeIds->contains($log->employee_id))->toBeFalse();
        }

        // Clean up for next iteration
        AttendanceLog::query()->delete();
    }

    // Clean up employees
    foreach ($employees as $employee) {
        $employee->user->forceDelete();
        $employee->forceDelete();
    }
})->group('property-test', 'hris-attendance', 'hr-filter');


test('Property 13: HR filter accuracy - date range filter returns only matching records', function () {
    $employee = Employee::factory()->create();

    // Run 5 iterations
    for ($i = 0; $i < 5; $i++) {
        // Use iteration offset to ensure unique dates across iterations
        $baseOffset = $i * 50;

        // Generate a date range
        $rangeLength = 10;
        $fromDate = Carbon::today()->subDays($baseOffset + 30);
        $toDate = $fromDate->copy()->addDays($rangeLength);

        // Create logs inside the range with unique dates
        $logsInsideRange = [];
        for ($j = 0; $j < 3; $j++) {
            $logDate = $fromDate->copy()->addDays($j + 1); // Unique dates: day 1, 2, 3
            $logsInsideRange[] = AttendanceLog::factory()->create([
                'employee_id' => $employee->id,
                'date' => $logDate,
                'check_in_time' => $logDate->copy()->setTime(8, 0, 0),
            ]);
        }

        // Create logs outside the range with unique dates
        $logsOutsideRange = [];
        // Before range
        $beforeDate = $fromDate->copy()->subDays(5);
        $logsOutsideRange[] = AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'date' => $beforeDate,
            'check_in_time' => $beforeDate->copy()->setTime(8, 0, 0),
        ]);
        // After range
        $afterDate = $toDate->copy()->addDays(5);
        $logsOutsideRange[] = AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'date' => $afterDate,
            'check_in_time' => $afterDate->copy()->setTime(8, 0, 0),
        ]);

        // Apply date range filter (simulating the resource filter)
        $filteredLogs = AttendanceLog::query()
            ->whereDate('date', '>=', $fromDate)
            ->whereDate('date', '<=', $toDate)
            ->get();

        // Property: All returned logs must be within the date range
        foreach ($filteredLogs as $log) {
            expect($log->date->gte($fromDate))->toBeTrue(
                "Log date {$log->date->toDateString()} should be >= {$fromDate->toDateString()}"
            );
            expect($log->date->lte($toDate))->toBeTrue(
                "Log date {$log->date->toDateString()} should be <= {$toDate->toDateString()}"
            );
        }

        // Property: Logs outside the range must NOT be returned
        $returnedIds = $filteredLogs->pluck('id')->toArray();
        foreach ($logsOutsideRange as $outsideLog) {
            expect(in_array($outsideLog->id, $returnedIds))->toBeFalse(
                "Log with date {$outsideLog->date->toDateString()} should not be in results"
            );
        }

        // Clean up for next iteration
        AttendanceLog::query()->delete();
    }

    // Clean up employee
    $employee->user->forceDelete();
    $employee->forceDelete();
})->group('property-test', 'hris-attendance', 'hr-filter');


test('Property 13: HR filter accuracy - status filter returns only matching records', function () {
    $employee = Employee::factory()->create();
    $statuses = AttendanceLog::getStatuses();

    // Run 5 iterations
    for ($i = 0; $i < 5; $i++) {
        // Create logs with different statuses
        $createdLogs = [];
        foreach ($statuses as $index => $status) {
            $logDate = Carbon::today()->subDays($index);
            $createdLogs[$status] = AttendanceLog::factory()->create([
                'employee_id' => $employee->id,
                'date' => $logDate,
                'check_in_time' => $logDate->copy()->setTime(8, 0, 0),
                'status' => $status,
            ]);
        }

        // Select a random status to filter by
        $targetStatus = fake()->randomElement($statuses);

        // Apply status filter using the model's scope (same as resource filter)
        $filteredLogs = AttendanceLog::query()
            ->forStatus($targetStatus)
            ->get();

        // Property: All returned logs must have the filtered status
        foreach ($filteredLogs as $log) {
            expect($log->status)->toBe($targetStatus);
        }

        // Property: Logs with other statuses must NOT be returned
        $otherStatuses = array_filter($statuses, fn ($s) => $s !== $targetStatus);
        $returnedIds = $filteredLogs->pluck('id')->toArray();
        foreach ($otherStatuses as $otherStatus) {
            if (isset($createdLogs[$otherStatus])) {
                expect(in_array($createdLogs[$otherStatus]->id, $returnedIds))->toBeFalse(
                    "Log with status {$otherStatus} should not be in results when filtering for {$targetStatus}"
                );
            }
        }

        // Clean up for next iteration
        AttendanceLog::query()->delete();
    }

    // Clean up employee
    $employee->user->forceDelete();
    $employee->forceDelete();
})->group('property-test', 'hris-attendance', 'hr-filter');


test('Property 13: HR filter accuracy - mode filter returns only matching records', function () {
    $employee = Employee::factory()->create();
    $modes = AttendanceLog::getModes();

    // Run 5 iterations
    for ($i = 0; $i < 5; $i++) {
        // Create logs with different modes
        $createdLogs = [];
        foreach ($modes as $index => $mode) {
            $logDate = Carbon::today()->subDays($index);
            $createdLogs[$mode] = AttendanceLog::factory()->create([
                'employee_id' => $employee->id,
                'date' => $logDate,
                'check_in_time' => $logDate->copy()->setTime(8, 0, 0),
                'mode' => $mode,
            ]);
        }

        // Select a random mode to filter by
        $targetMode = fake()->randomElement($modes);

        // Apply mode filter using the model's scope (same as resource filter)
        $filteredLogs = AttendanceLog::query()
            ->forMode($targetMode)
            ->get();

        // Property: All returned logs must have the filtered mode
        foreach ($filteredLogs as $log) {
            expect($log->mode)->toBe($targetMode);
        }

        // Property: Logs with other modes must NOT be returned
        $otherModes = array_filter($modes, fn ($m) => $m !== $targetMode);
        $returnedIds = $filteredLogs->pluck('id')->toArray();
        foreach ($otherModes as $otherMode) {
            if (isset($createdLogs[$otherMode])) {
                expect(in_array($createdLogs[$otherMode]->id, $returnedIds))->toBeFalse(
                    "Log with mode {$otherMode} should not be in results when filtering for {$targetMode}"
                );
            }
        }

        // Clean up for next iteration
        AttendanceLog::query()->delete();
    }

    // Clean up employee
    $employee->user->forceDelete();
    $employee->forceDelete();
})->group('property-test', 'hris-attendance', 'hr-filter');


test('Property 13: HR filter accuracy - combined filters return only records matching ALL criteria', function () {
    // Create multiple employees
    $employees = Employee::factory()->count(3)->create();
    $statuses = AttendanceLog::getStatuses();
    $modes = AttendanceLog::getModes();

    // Run 5 iterations
    for ($i = 0; $i < 5; $i++) {
        // Use iteration offset to ensure unique dates across iterations
        $baseOffset = $i * 100;

        // Generate a date range
        $fromDate = Carbon::today()->subDays($baseOffset + 20);
        $toDate = $fromDate->copy()->addDays(15);

        // Create diverse attendance logs with unique dates per employee
        $dateCounter = 0;
        foreach ($employees as $employeeIndex => $employee) {
            // Create one log per status/mode combination with unique dates
            foreach ($statuses as $statusIndex => $status) {
                foreach ($modes as $modeIndex => $mode) {
                    // Calculate unique date offset for this combination
                    $dateOffset = ($statusIndex * count($modes) + $modeIndex);
                    $insideDate = $fromDate->copy()->addDays($dateOffset % 15);

                    // Use a unique date by adding employee offset
                    $uniqueDate = $insideDate->copy()->subDays($employeeIndex * 20);

                    AttendanceLog::factory()->create([
                        'employee_id' => $employee->id,
                        'date' => $uniqueDate,
                        'check_in_time' => $uniqueDate->copy()->setTime(8, 0, 0),
                        'status' => $status,
                        'mode' => $mode,
                    ]);
                }
            }
        }

        // Select random filter criteria
        $targetEmployee = $employees->random();
        $targetStatus = fake()->randomElement($statuses);
        $targetMode = fake()->randomElement($modes);

        // Apply combined filters (simulating the resource filters)
        $filteredLogs = AttendanceLog::query()
            ->forEmployee($targetEmployee->id)
            ->forStatus($targetStatus)
            ->forMode($targetMode)
            ->get();

        // Property: All returned logs must match ALL filter criteria
        foreach ($filteredLogs as $log) {
            // Must match employee
            expect($log->employee_id)->toBe($targetEmployee->id,
                "Log employee_id {$log->employee_id} should match target {$targetEmployee->id}"
            );

            // Must match status
            expect($log->status)->toBe($targetStatus,
                "Log status {$log->status} should match target {$targetStatus}"
            );

            // Must match mode
            expect($log->mode)->toBe($targetMode,
                "Log mode {$log->mode} should match target {$targetMode}"
            );
        }

        // Clean up for next iteration
        AttendanceLog::query()->delete();
    }

    // Clean up employees
    foreach ($employees as $employee) {
        $employee->user->forceDelete();
        $employee->forceDelete();
    }
})->group('property-test', 'hris-attendance', 'hr-filter');
