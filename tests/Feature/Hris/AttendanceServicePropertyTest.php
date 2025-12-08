<?php

/**
 * Property-Based Tests for AttendanceService
 *
 * These tests verify correctness properties defined in the design document
 * using property-based testing approach with random data generation.
 */

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use App\Services\Hris\AttendanceService;
use Carbon\Carbon;

beforeEach(function () {
    // Clean up before each test
    AttendanceLog::query()->delete();
    Carbon::setTestNow(Carbon::now());
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * **Feature: hris-phase2-attendance, Property 1: Check-in creates AttendanceLog with correct data**
 *
 * For any employee and valid check-in data (timestamp, source, mode, optional GPS),
 * performing a check-in SHALL result in an AttendanceLog record being created with
 * the exact timestamp, source, and mode provided.
 *
 * **Validates: Requirements 1.1, 1.10**
 */
test('Property 1: Check-in creates AttendanceLog with correct data', function () {
    $service = new AttendanceService();
    $employee = Employee::factory()->create();

    // Run 100 iterations with random check-in data
    for ($i = 0; $i < 100; $i++) {
        // Set a unique date for each iteration to avoid duplicate check-in errors
        $testDate = Carbon::today()->addDays($i);
        Carbon::setTestNow($testDate->copy()->setTime(8, 0, 0));

        // Generate random check-in data
        $source = fake()->randomElement(AttendanceLog::getSources());
        $mode = fake()->randomElement(AttendanceLog::getModes());
        $latitude = fake()->optional(0.8)->randomFloat(8, -90, 90);
        $longitude = $latitude !== null ? fake()->randomFloat(8, -180, 180) : null;

        $data = [
            'source' => $source,
            'mode' => $mode,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];

        // Perform check-in
        $attendanceLog = $service->checkIn($employee, $data);

        // Refresh from database
        $attendanceLog->refresh();

        // Property: AttendanceLog must be created for the correct employee
        expect($attendanceLog->employee_id)->toBe($employee->id);

        // Property: Date must be today's date
        expect($attendanceLog->date->toDateString())->toBe($testDate->toDateString());

        // Property: Source must match the provided value
        expect($attendanceLog->source)->toBe($source);

        // Property: Mode must match the provided value
        expect($attendanceLog->mode)->toBe($mode);

        // Property: Check-in time must be set
        expect($attendanceLog->check_in_time)->not->toBeNull();

        // Property: GPS coordinates must match if provided
        if ($latitude !== null) {
            expect((float) $attendanceLog->check_in_latitude)->toEqualWithDelta($latitude, 0.00000001);
            expect((float) $attendanceLog->check_in_longitude)->toEqualWithDelta($longitude, 0.00000001);
        } else {
            expect($attendanceLog->check_in_latitude)->toBeNull();
            expect($attendanceLog->check_in_longitude)->toBeNull();
        }

        // Property: Check-out fields must be null after check-in only
        expect($attendanceLog->check_out_time)->toBeNull();

        // Clean up for next iteration
        $attendanceLog->forceDelete();
    }

    // Clean up employee
    $employee->user->forceDelete();
    $employee->forceDelete();
})->group('property-test', 'hris-attendance');

/**
 * **Feature: hris-phase2-attendance, Property 3: Duplicate check-in rejection**
 *
 * For any employee who has already checked in for the current day,
 * attempting another check-in SHALL be rejected and the existing
 * AttendanceLog SHALL remain unchanged.
 *
 * **Validates: Requirements 1.3**
 */
test('Property 3: Duplicate check-in rejection', function () {
    $service = new AttendanceService();
    $employee = Employee::factory()->create();

    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Set a unique date for each iteration
        $testDate = Carbon::today()->addDays($i);
        Carbon::setTestNow($testDate->copy()->setTime(8, 0, 0));

        // First check-in should succeed
        $firstData = [
            'source' => fake()->randomElement(AttendanceLog::getSources()),
            'mode' => fake()->randomElement(AttendanceLog::getModes()),
            'latitude' => fake()->randomFloat(8, -90, 90),
            'longitude' => fake()->randomFloat(8, -180, 180),
        ];

        $firstLog = $service->checkIn($employee, $firstData);
        $originalCheckInTime = $firstLog->check_in_time->copy();
        $originalSource = $firstLog->source;
        $originalMode = $firstLog->mode;

        // Second check-in attempt should fail
        $secondData = [
            'source' => fake()->randomElement(AttendanceLog::getSources()),
            'mode' => fake()->randomElement(AttendanceLog::getModes()),
        ];

        $exceptionThrown = false;
        try {
            $service->checkIn($employee, $secondData);
        } catch (\Exception $e) {
            $exceptionThrown = true;
            expect($e->getMessage())->toBe('Employee has already checked in for today.');
        }

        // Property: Exception must be thrown for duplicate check-in
        expect($exceptionThrown)->toBeTrue();

        // Property: Original log must remain unchanged
        $firstLog->refresh();
        expect($firstLog->check_in_time->toDateTimeString())->toBe($originalCheckInTime->toDateTimeString());
        expect($firstLog->source)->toBe($originalSource);
        expect($firstLog->mode)->toBe($originalMode);

        // Property: Only one attendance log should exist for this date
        $logsCount = AttendanceLog::forEmployee($employee->id)
            ->forDate($testDate)
            ->count();
        expect($logsCount)->toBe(1);

        // Clean up for next iteration
        $firstLog->forceDelete();
    }

    // Clean up employee
    $employee->user->forceDelete();
    $employee->forceDelete();
})->group('property-test', 'hris-attendance');

/**
 * **Feature: hris-phase2-attendance, Property 2: Check-out updates existing AttendanceLog**
 *
 * For any employee with an existing check-in for the day, performing a check-out
 * SHALL update that AttendanceLog record with the check-out timestamp without
 * creating a new record.
 *
 * **Validates: Requirements 1.2**
 */
test('Property 2: Check-out updates existing AttendanceLog', function () {
    $service = new AttendanceService();
    $employee = Employee::factory()->create();

    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Set a unique date for each iteration
        $testDate = Carbon::today()->addDays($i);
        Carbon::setTestNow($testDate->copy()->setTime(8, 0, 0));

        // First, check in
        $checkInData = [
            'source' => fake()->randomElement(AttendanceLog::getSources()),
            'mode' => fake()->randomElement(AttendanceLog::getModes()),
        ];
        $attendanceLog = $service->checkIn($employee, $checkInData);
        $originalId = $attendanceLog->id;
        $originalCheckInTime = $attendanceLog->check_in_time->copy();

        // Move time forward for check-out
        Carbon::setTestNow($testDate->copy()->setTime(17, 0, 0));

        // Generate random check-out data
        $checkOutLatitude = fake()->optional(0.8)->randomFloat(8, -90, 90);
        $checkOutLongitude = $checkOutLatitude !== null ? fake()->randomFloat(8, -180, 180) : null;

        $checkOutData = [
            'latitude' => $checkOutLatitude,
            'longitude' => $checkOutLongitude,
        ];

        // Perform check-out
        $updatedLog = $service->checkOut($employee, $checkOutData);

        // Property: Same record must be updated (no new record created)
        expect($updatedLog->id)->toBe($originalId);

        // Property: Check-in time must remain unchanged
        expect($updatedLog->check_in_time->toDateTimeString())->toBe($originalCheckInTime->toDateTimeString());

        // Property: Check-out time must be set
        expect($updatedLog->check_out_time)->not->toBeNull();

        // Property: GPS coordinates must match if provided
        if ($checkOutLatitude !== null) {
            expect((float) $updatedLog->check_out_latitude)->toEqualWithDelta($checkOutLatitude, 0.00000001);
            expect((float) $updatedLog->check_out_longitude)->toEqualWithDelta($checkOutLongitude, 0.00000001);
        } else {
            expect($updatedLog->check_out_latitude)->toBeNull();
            expect($updatedLog->check_out_longitude)->toBeNull();
        }

        // Property: Only one attendance log should exist for this date
        $logsCount = AttendanceLog::forEmployee($employee->id)
            ->forDate($testDate)
            ->count();
        expect($logsCount)->toBe(1);

        // Clean up for next iteration
        $updatedLog->forceDelete();
    }

    // Clean up employee
    $employee->user->forceDelete();
    $employee->forceDelete();
})->group('property-test', 'hris-attendance');

/**
 * **Feature: hris-phase2-attendance, Property 4: Check-out without check-in rejection**
 *
 * For any employee who has not checked in for the current day, attempting a
 * check-out SHALL be rejected and no AttendanceLog record SHALL be created
 * or modified.
 *
 * **Validates: Requirements 1.4**
 */
test('Property 4: Check-out without check-in rejection', function () {
    $service = new AttendanceService();
    $employee = Employee::factory()->create();

    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Set a unique date for each iteration
        $testDate = Carbon::today()->addDays($i);
        Carbon::setTestNow($testDate->copy()->setTime(17, 0, 0));

        // Attempt check-out without check-in
        $checkOutData = [
            'latitude' => fake()->randomFloat(8, -90, 90),
            'longitude' => fake()->randomFloat(8, -180, 180),
        ];

        $exceptionThrown = false;
        try {
            $service->checkOut($employee, $checkOutData);
        } catch (\Exception $e) {
            $exceptionThrown = true;
            expect($e->getMessage())->toBe('Employee has not checked in for today.');
        }

        // Property: Exception must be thrown
        expect($exceptionThrown)->toBeTrue();

        // Property: No attendance log should exist for this date
        $logsCount = AttendanceLog::forEmployee($employee->id)
            ->forDate($testDate)
            ->count();
        expect($logsCount)->toBe(0);
    }

    // Clean up employee
    $employee->user->forceDelete();
    $employee->forceDelete();
})->group('property-test', 'hris-attendance');


/**
 * **Feature: hris-phase2-attendance, Property 8: WFH quota validation**
 *
 * For any employee selecting WFH mode during check-in, if the employee has
 * already used their WFH quota for the current week (1 day), the check-in
 * SHALL be rejected.
 *
 * **Validates: Requirements 1.11, 1.12**
 */
test('Property 8: WFH quota validation', function () {
    $service = new AttendanceService();
    $employee = Employee::factory()->create();

    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Set a random Monday as the start of the week
        $weekStart = Carbon::today()->addWeeks($i)->startOfWeek(Carbon::MONDAY);

        // First, use the WFH quota on a random day of the week (Mon-Fri)
        $firstWfhDay = $weekStart->copy()->addDays(fake()->numberBetween(0, 4));
        Carbon::setTestNow($firstWfhDay->copy()->setTime(8, 0, 0));

        $firstWfhLog = $service->checkIn($employee, [
            'source' => AttendanceLog::SOURCE_WEB,
            'mode' => AttendanceLog::MODE_WFH,
        ]);

        // Property: First WFH check-in should succeed
        expect($firstWfhLog)->toBeInstanceOf(AttendanceLog::class);
        expect($firstWfhLog->mode)->toBe(AttendanceLog::MODE_WFH);

        // Now try to use WFH on another day in the same week
        $secondWfhDay = $weekStart->copy()->addDays(fake()->numberBetween(0, 6));
        // Make sure it's a different day
        while ($secondWfhDay->toDateString() === $firstWfhDay->toDateString()) {
            $secondWfhDay = $weekStart->copy()->addDays(fake()->numberBetween(0, 6));
        }
        Carbon::setTestNow($secondWfhDay->copy()->setTime(8, 0, 0));

        // Property: Second WFH check-in in the same week should be rejected
        $exceptionThrown = false;
        try {
            $service->checkIn($employee, [
                'source' => AttendanceLog::SOURCE_WEB,
                'mode' => AttendanceLog::MODE_WFH,
            ]);
        } catch (\Exception $e) {
            $exceptionThrown = true;
            expect($e->getMessage())->toBe('WFH quota exhausted for this week.');
        }

        expect($exceptionThrown)->toBeTrue();

        // Property: Only one WFH log should exist for this week
        $wfhLogsCount = AttendanceLog::forEmployee($employee->id)
            ->forMode(AttendanceLog::MODE_WFH)
            ->forDateRange($weekStart, $weekStart->copy()->endOfWeek(Carbon::SUNDAY))
            ->count();
        expect($wfhLogsCount)->toBe(1);

        // Clean up for next iteration
        AttendanceLog::forEmployee($employee->id)->forceDelete();
    }

    // Clean up employee
    $employee->user->forceDelete();
    $employee->forceDelete();
})->group('property-test', 'hris-attendance', 'wfh-quota');


/**
 * **Feature: hris-phase2-attendance, Property 9: WFH quota decrement**
 *
 * For any successful WFH check-in, the employee's remaining WFH quota for
 * the current week SHALL decrease by exactly 1.
 *
 * **Validates: Requirements 1.13**
 */
test('Property 9: WFH quota decrement', function () {
    $service = new AttendanceService();
    $employee = Employee::factory()->create();

    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Set a random Monday as the start of the week
        $weekStart = Carbon::today()->addWeeks($i)->startOfWeek(Carbon::MONDAY);
        $testDay = $weekStart->copy()->addDays(fake()->numberBetween(0, 4)); // Mon-Fri
        Carbon::setTestNow($testDay->copy()->setTime(8, 0, 0));

        // Property: Before WFH check-in, quota should be 1
        $quotaBefore = $service->getRemainingWfhQuota($employee, $testDay);
        expect($quotaBefore)->toBe(1);

        // Perform WFH check-in
        $attendanceLog = $service->checkIn($employee, [
            'source' => fake()->randomElement(AttendanceLog::getSources()),
            'mode' => AttendanceLog::MODE_WFH,
        ]);

        // Property: After WFH check-in, quota should decrease by exactly 1
        $quotaAfter = $service->getRemainingWfhQuota($employee, $testDay);
        expect($quotaAfter)->toBe($quotaBefore - 1);
        expect($quotaAfter)->toBe(0);

        // Property: The attendance log should be recorded as WFH
        expect($attendanceLog->mode)->toBe(AttendanceLog::MODE_WFH);

        // Clean up for next iteration
        AttendanceLog::forEmployee($employee->id)->forceDelete();
    }

    // Clean up employee
    $employee->user->forceDelete();
    $employee->forceDelete();
})->group('property-test', 'hris-attendance', 'wfh-quota');


/**
 * **Feature: hris-phase2-attendance, Property 10: WFH quota weekly reset**
 *
 * For any employee, the WFH quota SHALL be calculated as 1 minus the number
 * of WFH days used in the current week (Monday to Sunday). Unused quota from
 * previous weeks SHALL NOT carry over.
 *
 * **Validates: Requirements 1.14**
 */
test('Property 10: WFH quota weekly reset', function () {
    $service = new AttendanceService();
    $employee = Employee::factory()->create();

    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Set up two consecutive weeks
        $week1Start = Carbon::today()->addWeeks($i * 2)->startOfWeek(Carbon::MONDAY);
        $week2Start = $week1Start->copy()->addWeek();

        // Use WFH quota in week 1
        $week1Day = $week1Start->copy()->addDays(fake()->numberBetween(0, 4));
        Carbon::setTestNow($week1Day->copy()->setTime(8, 0, 0));

        $service->checkIn($employee, [
            'source' => AttendanceLog::SOURCE_WEB,
            'mode' => AttendanceLog::MODE_WFH,
        ]);

        // Property: Week 1 quota should be 0 after using WFH
        $week1Quota = $service->getRemainingWfhQuota($employee, $week1Day);
        expect($week1Quota)->toBe(0);

        // Move to week 2
        $week2Day = $week2Start->copy()->addDays(fake()->numberBetween(0, 4));
        Carbon::setTestNow($week2Day->copy()->setTime(8, 0, 0));

        // Property: Week 2 quota should be reset to 1 (no carry over)
        $week2Quota = $service->getRemainingWfhQuota($employee, $week2Day);
        expect($week2Quota)->toBe(1);

        // Property: Should be able to use WFH in week 2
        $week2Log = $service->checkIn($employee, [
            'source' => AttendanceLog::SOURCE_WEB,
            'mode' => AttendanceLog::MODE_WFH,
        ]);
        expect($week2Log->mode)->toBe(AttendanceLog::MODE_WFH);

        // Property: Week 2 quota should now be 0
        $week2QuotaAfter = $service->getRemainingWfhQuota($employee, $week2Day);
        expect($week2QuotaAfter)->toBe(0);

        // Property: Week 1 quota should still be 0 (independent of week 2)
        $week1QuotaStill = $service->getRemainingWfhQuota($employee, $week1Day);
        expect($week1QuotaStill)->toBe(0);

        // Clean up for next iteration
        AttendanceLog::forEmployee($employee->id)->forceDelete();
    }

    // Clean up employee
    $employee->user->forceDelete();
    $employee->forceDelete();
})->group('property-test', 'hris-attendance', 'wfh-quota');


/**
 * **Feature: hris-phase2-attendance, Property 11: Attendance history filtering and ordering**
 *
 * For any employee and date range, requesting attendance history SHALL return
 * only AttendanceLogs within that range, ordered by date descending.
 *
 * **Validates: Requirements 2.2**
 */
test('Property 11: Attendance history filtering and ordering', function () {
    $service = new AttendanceService();
    $employee = Employee::factory()->create();

    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Use iteration offset to ensure unique date ranges across iterations
        $baseOffset = $i * 100;

        // Generate a random date range (5-15 days to ensure enough room for logs)
        $rangeLength = fake()->numberBetween(5, 15);
        $fromDate = Carbon::today()->subDays($baseOffset + $rangeLength);
        $toDate = Carbon::today()->subDays($baseOffset);

        // Track used dates to ensure uniqueness
        $usedDates = [];

        // Create attendance logs both inside and outside the range
        $logsInsideRange = [];
        $logsOutsideRange = [];

        // Create 3-5 logs inside the range (limited to ensure unique dates)
        $insideCount = min(fake()->numberBetween(3, 5), $rangeLength);
        for ($j = 0; $j < $insideCount; $j++) {
            // Find a unique date within the range
            $attempts = 0;
            do {
                $dayOffset = fake()->numberBetween(0, $rangeLength);
                $logDate = $fromDate->copy()->addDays($dayOffset);
                $dateString = $logDate->toDateString();
                $attempts++;
            } while (in_array($dateString, $usedDates) && $attempts < 50);

            if ($attempts >= 50) {
                continue; // Skip if we can't find a unique date
            }

            $usedDates[] = $dateString;

            $log = AttendanceLog::factory()->create([
                'employee_id' => $employee->id,
                'date' => $logDate,
                'check_in_time' => $logDate->copy()->setTime(8, 0, 0),
            ]);
            $logsInsideRange[] = $log;
        }

        // Create 2 logs outside the range (before and after)
        // Before the range
        $beforeDate = $fromDate->copy()->subDays(fake()->numberBetween(1, 10));
        $usedDates[] = $beforeDate->toDateString();
        $logsOutsideRange[] = AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'date' => $beforeDate,
            'check_in_time' => $beforeDate->copy()->setTime(8, 0, 0),
        ]);

        // After the range
        $afterDate = $toDate->copy()->addDays(fake()->numberBetween(1, 10));
        $usedDates[] = $afterDate->toDateString();
        $logsOutsideRange[] = AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'date' => $afterDate,
            'check_in_time' => $afterDate->copy()->setTime(8, 0, 0),
        ]);

        // Get history using the service
        $history = $service->getHistory($employee, $fromDate, $toDate);

        // Property: All returned logs must be within the date range
        foreach ($history as $log) {
            expect($log->date->gte($fromDate))->toBeTrue(
                "Log date {$log->date->toDateString()} should be >= {$fromDate->toDateString()}"
            );
            expect($log->date->lte($toDate))->toBeTrue(
                "Log date {$log->date->toDateString()} should be <= {$toDate->toDateString()}"
            );
        }

        // Property: All logs inside the range must be returned
        expect($history->count())->toBe(count($logsInsideRange));

        // Property: Logs must be ordered by date descending
        $previousDate = null;
        foreach ($history as $log) {
            if ($previousDate !== null) {
                expect($log->date->lte($previousDate))->toBeTrue(
                    "Logs should be ordered by date descending: {$log->date->toDateString()} should be <= {$previousDate->toDateString()}"
                );
            }
            $previousDate = $log->date;
        }

        // Property: Logs outside the range must NOT be returned
        $returnedIds = $history->pluck('id')->toArray();
        foreach ($logsOutsideRange as $outsideLog) {
            expect(in_array($outsideLog->id, $returnedIds))->toBeFalse(
                "Log with date {$outsideLog->date->toDateString()} should not be in results"
            );
        }

        // Clean up for next iteration
        AttendanceLog::forEmployee($employee->id)->forceDelete();
    }

    // Clean up employee
    $employee->user->forceDelete();
    $employee->forceDelete();
})->group('property-test', 'hris-attendance', 'attendance-history');


/**
 * **Feature: hris-phase2-attendance, Property 12: Attendance status computation**
 *
 * For any AttendanceLog, the computed status SHALL be determined based on
 * check-in time, check-out time, and any associated leave records.
 *
 * **Validates: Requirements 2.3**
 */
test('Property 12: Attendance status computation', function () {
    $service = new AttendanceService();
    $employee = Employee::factory()->create();

    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Use unique date for each iteration
        $testDate = Carbon::today()->subDays($i);

        // Randomly choose a scenario to test
        $scenario = fake()->randomElement(['present', 'late', 'half_day', 'leave', 'absent']);

        switch ($scenario) {
            case 'present':
                // Check-in on time (before 9:15 AM), full day work
                $checkInTime = $testDate->copy()->setTime(
                    fake()->numberBetween(7, 8),
                    fake()->numberBetween(0, 59),
                    0
                );
                $checkOutTime = $checkInTime->copy()->addHours(fake()->numberBetween(8, 10));

                $log = AttendanceLog::factory()->create([
                    'employee_id' => $employee->id,
                    'date' => $testDate,
                    'check_in_time' => $checkInTime,
                    'check_out_time' => $checkOutTime,
                    'status' => AttendanceLog::STATUS_PRESENT, // Initial status
                ]);

                $computedStatus = $service->computeStatus($log);

                // Property: On-time check-in with full day work should be PRESENT
                expect($computedStatus)->toBe(AttendanceLog::STATUS_PRESENT);
                break;

            case 'late':
                // Check-in after 9:15 AM (work start + grace period), full day work
                $checkInTime = $testDate->copy()->setTime(
                    fake()->numberBetween(9, 11),
                    fake()->numberBetween(16, 59), // After grace period (15 min)
                    0
                );
                // Ensure at least 8 hours of work
                $checkOutTime = $checkInTime->copy()->addHours(fake()->numberBetween(8, 10));

                $log = AttendanceLog::factory()->create([
                    'employee_id' => $employee->id,
                    'date' => $testDate,
                    'check_in_time' => $checkInTime,
                    'check_out_time' => $checkOutTime,
                    'status' => AttendanceLog::STATUS_PRESENT, // Initial status
                ]);

                $computedStatus = $service->computeStatus($log);

                // Property: Late check-in with full day work should be LATE
                expect($computedStatus)->toBe(AttendanceLog::STATUS_LATE);
                break;

            case 'half_day':
                // Check-in on time but work less than 8 hours (but at least 4)
                $checkInTime = $testDate->copy()->setTime(8, 0, 0);
                $workHours = fake()->numberBetween(4, 7);
                $checkOutTime = $checkInTime->copy()->addHours($workHours);

                $log = AttendanceLog::factory()->create([
                    'employee_id' => $employee->id,
                    'date' => $testDate,
                    'check_in_time' => $checkInTime,
                    'check_out_time' => $checkOutTime,
                    'status' => AttendanceLog::STATUS_PRESENT, // Initial status
                ]);

                $computedStatus = $service->computeStatus($log);

                // Property: Less than full day hours should be HALF_DAY
                expect($computedStatus)->toBe(AttendanceLog::STATUS_HALF_DAY);
                break;

            case 'leave':
                // Employee has approved leave for this date
                $leaveRequest = \App\Models\Hris\LeaveRequest::factory()->create([
                    'employee_id' => $employee->id,
                    'start_date' => $testDate->copy()->subDays(1),
                    'end_date' => $testDate->copy()->addDays(1),
                    'status' => \App\Models\Hris\LeaveRequest::STATUS_APPROVED,
                ]);

                // Create an attendance log (even though on leave)
                $log = AttendanceLog::factory()->create([
                    'employee_id' => $employee->id,
                    'date' => $testDate,
                    'check_in_time' => $testDate->copy()->setTime(8, 0, 0),
                    'check_out_time' => $testDate->copy()->setTime(17, 0, 0),
                    'status' => AttendanceLog::STATUS_PRESENT, // Initial status
                ]);

                $computedStatus = $service->computeStatus($log);

                // Property: Employee with approved leave should have LEAVE status
                expect($computedStatus)->toBe(AttendanceLog::STATUS_LEAVE);

                // Clean up leave request
                $leaveRequest->forceDelete();
                break;

            case 'absent':
                // No check-in for the day
                $log = AttendanceLog::factory()->create([
                    'employee_id' => $employee->id,
                    'date' => $testDate,
                    'check_in_time' => null,
                    'check_out_time' => null,
                    'status' => AttendanceLog::STATUS_ABSENT, // Initial status
                ]);

                $computedStatus = $service->computeStatus($log);

                // Property: No check-in should be ABSENT
                expect($computedStatus)->toBe(AttendanceLog::STATUS_ABSENT);
                break;
        }

        // Clean up for next iteration
        AttendanceLog::forEmployee($employee->id)->forceDelete();
    }

    // Clean up employee
    $employee->user->forceDelete();
    $employee->forceDelete();
})->group('property-test', 'hris-attendance', 'status-computation');


/**
 * **Feature: hris-phase2-attendance, Property 18: Team attendance scope**
 *
 * For any manager viewing team attendance, the returned data SHALL include
 * only employees who are direct reports of that manager.
 *
 * **Validates: Requirements 6.1**
 */
test('Property 18: Team attendance scope', function () {
    $service = new AttendanceService();

    // Run 100 iterations
    for ($i = 0; $i < 100; $i++) {
        // Create a manager employee
        $manager = Employee::factory()->create();

        // Create a random number of direct reports (1-5)
        $numDirectReports = fake()->numberBetween(1, 5);
        $directReports = [];

        for ($j = 0; $j < $numDirectReports; $j++) {
            $employee = Employee::factory()->create();

            // Create an active primary position with this manager
            \App\Models\Hris\EmployeePosition::factory()->create([
                'employee_id' => $employee->id,
                'manager_id' => $manager->id,
                'is_primary' => true,
                'end_date' => null, // Active position
            ]);

            $directReports[] = $employee;
        }

        // Create some employees who are NOT direct reports of this manager
        $numOtherEmployees = fake()->numberBetween(1, 3);
        $otherEmployees = [];

        for ($j = 0; $j < $numOtherEmployees; $j++) {
            $employee = Employee::factory()->create();

            // Create a position with a different manager (or no manager)
            $otherManager = fake()->boolean(50) ? Employee::factory()->create() : null;
            \App\Models\Hris\EmployeePosition::factory()->create([
                'employee_id' => $employee->id,
                'manager_id' => $otherManager?->id,
                'is_primary' => true,
                'end_date' => null,
            ]);

            $otherEmployees[] = $employee;
        }

        // Create attendance logs for all employees
        $testDate = Carbon::today()->subDays($i);
        Carbon::setTestNow($testDate->copy()->setTime(8, 0, 0));

        foreach (array_merge($directReports, $otherEmployees) as $employee) {
            AttendanceLog::factory()->create([
                'employee_id' => $employee->id,
                'date' => $testDate,
                'check_in_time' => $testDate->copy()->setTime(8, 0, 0),
            ]);
        }

        // Get team attendance for the manager
        $teamAttendance = $service->getTeamAttendance($manager, $testDate);

        // Property: The number of returned records must equal the number of direct reports
        expect($teamAttendance->count())->toBe($numDirectReports);

        // Property: All returned employee IDs must be direct reports
        $returnedEmployeeIds = $teamAttendance->pluck('employee_id')->toArray();
        $directReportIds = array_map(fn ($e) => $e->id, $directReports);

        foreach ($returnedEmployeeIds as $employeeId) {
            expect(in_array($employeeId, $directReportIds))->toBeTrue(
                "Employee ID {$employeeId} should be a direct report of the manager"
            );
        }

        // Property: All direct reports must be included in the results
        foreach ($directReportIds as $directReportId) {
            expect(in_array($directReportId, $returnedEmployeeIds))->toBeTrue(
                "Direct report ID {$directReportId} should be in the team attendance results"
            );
        }

        // Property: No other employees should be included
        $otherEmployeeIds = array_map(fn ($e) => $e->id, $otherEmployees);
        foreach ($otherEmployeeIds as $otherEmployeeId) {
            expect(in_array($otherEmployeeId, $returnedEmployeeIds))->toBeFalse(
                "Employee ID {$otherEmployeeId} should NOT be in the team attendance results"
            );
        }

        // Clean up for next iteration
        AttendanceLog::query()->delete();
        \App\Models\Hris\EmployeePosition::query()->delete();

        // Clean up employees and their users
        foreach (array_merge($directReports, $otherEmployees, [$manager]) as $employee) {
            $employee->user?->forceDelete();
            $employee->forceDelete();
        }

        // Clean up other managers created
        Employee::query()->each(function ($e) {
            $e->user?->forceDelete();
            $e->forceDelete();
        });
    }
})->group('property-test', 'hris-attendance', 'team-attendance');
