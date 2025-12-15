<?php

/**
 * Property-Based Tests for AttendanceLog Model
 *
 * These tests verify correctness properties defined in the design document
 * using property-based testing approach with random data generation.
 */

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;

beforeEach(function () {
    // Clean up before each test
    AttendanceLog::query()->delete();
});

/**
 * **Feature: hris-phase2-attendance, Property 5: GPS coordinates storage**
 *
 * For any check-in or check-out with GPS coordinates provided, the latitude
 * and longitude values SHALL be stored in the corresponding AttendanceLog
 * fields with the exact values provided.
 *
 * **Validates: Requirements 1.5, 1.6**
 */
test('Property 5: GPS coordinates storage', function () {
    // Create a single employee for all iterations to speed up tests
    $employee = Employee::factory()->create();

    // Run 5 iterations with random GPS coordinates
    for ($i = 0; $i < 5; $i++) {
        // Generate random GPS coordinates within valid ranges
        // Latitude: -90 to 90
        // Longitude: -180 to 180
        $checkInLatitude = fake()->randomFloat(8, -90, 90);
        $checkInLongitude = fake()->randomFloat(8, -180, 180);
        $checkOutLatitude = fake()->randomFloat(8, -90, 90);
        $checkOutLongitude = fake()->randomFloat(8, -180, 180);

        // Create attendance log with GPS coordinates
        $attendanceLog = AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => now()->addDays($i)->toDateString(), // Different date each iteration
            'mode' => fake()->randomElement(AttendanceLog::getModes()),
            'check_in_time' => now()->setTime(8, 0),
            'check_in_latitude' => $checkInLatitude,
            'check_in_longitude' => $checkInLongitude,
            'check_out_time' => now()->setTime(17, 0),
            'check_out_latitude' => $checkOutLatitude,
            'check_out_longitude' => $checkOutLongitude,
            'source' => fake()->randomElement(AttendanceLog::getSources()),
            'status' => AttendanceLog::STATUS_PRESENT,
        ]);

        // Refresh from database to ensure we're testing persisted values
        $attendanceLog->refresh();

        // Property: Check-in latitude must match the provided value
        expect((float) $attendanceLog->check_in_latitude)->toEqualWithDelta(
            $checkInLatitude,
            0.00000001,
            "Check-in latitude was not stored correctly"
        );

        // Property: Check-in longitude must match the provided value
        expect((float) $attendanceLog->check_in_longitude)->toEqualWithDelta(
            $checkInLongitude,
            0.00000001,
            "Check-in longitude was not stored correctly"
        );

        // Property: Check-out latitude must match the provided value
        expect((float) $attendanceLog->check_out_latitude)->toEqualWithDelta(
            $checkOutLatitude,
            0.00000001,
            "Check-out latitude was not stored correctly"
        );

        // Property: Check-out longitude must match the provided value
        expect((float) $attendanceLog->check_out_longitude)->toEqualWithDelta(
            $checkOutLongitude,
            0.00000001,
            "Check-out longitude was not stored correctly"
        );

        // Clean up attendance log for next iteration
        $attendanceLog->forceDelete();
    }

    // Clean up employee at the end
    $employee->user->forceDelete();
    $employee->forceDelete();
})->group('property-test', 'hris-attendance');

/**
 * Test that GPS coordinates can be null when not provided.
 */
test('GPS coordinates can be null when not provided', function () {
    // Create a single employee for all iterations
    $employee = Employee::factory()->create();

    // Run 5 iterations
    for ($i = 0; $i < 5; $i++) {
        // Create attendance log without GPS coordinates
        $attendanceLog = AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => now()->addDays($i)->toDateString(),
            'mode' => AttendanceLog::MODE_OFFICE,
            'check_in_time' => now()->setTime(8, 0),
            'check_in_latitude' => null,
            'check_in_longitude' => null,
            'check_out_time' => now()->setTime(17, 0),
            'check_out_latitude' => null,
            'check_out_longitude' => null,
            'source' => AttendanceLog::SOURCE_WEB,
            'status' => AttendanceLog::STATUS_PRESENT,
        ]);

        // Refresh from database
        $attendanceLog->refresh();

        // Property: GPS coordinates should be null when not provided
        expect($attendanceLog->check_in_latitude)->toBeNull();
        expect($attendanceLog->check_in_longitude)->toBeNull();
        expect($attendanceLog->check_out_latitude)->toBeNull();
        expect($attendanceLog->check_out_longitude)->toBeNull();

        // Clean up attendance log for next iteration
        $attendanceLog->forceDelete();
    }

    // Clean up employee at the end
    $employee->user->forceDelete();
    $employee->forceDelete();
})->group('property-test', 'hris-attendance');

/**
 * Test that the employee relationship works correctly.
 */
test('AttendanceLog belongs to Employee', function () {
    // Run 5 iterations with different employees
    for ($i = 0; $i < 5; $i++) {
        // Create an employee
        $employee = Employee::factory()->create();

        // Create attendance log for the employee
        $attendanceLog = AttendanceLog::factory()
            ->for($employee)
            ->forDate(now()->addDays($i))
            ->create();

        // Refresh from database
        $attendanceLog->refresh();

        // Property: The employee relationship should return the correct employee
        expect($attendanceLog->employee->id)->toBe($employee->id);
        expect($attendanceLog->employee_id)->toBe($employee->id);

        // Clean up for next iteration
        $attendanceLog->forceDelete();
        $employee->user->forceDelete();
        $employee->forceDelete();
    }
})->group('property-test', 'hris-attendance');

/**
 * Test that scopes filter correctly.
 */
test('AttendanceLog scopes filter correctly', function () {
    // Clean up any existing data first
    AttendanceLog::query()->delete();

    // Create employees
    $employee1 = Employee::factory()->create();
    $employee2 = Employee::factory()->create();

    // Create attendance logs with different dates, employees, and statuses
    $log1 = AttendanceLog::factory()
        ->for($employee1)
        ->forDate(now()->subDays(2))
        ->create(['status' => AttendanceLog::STATUS_PRESENT]);

    $log2 = AttendanceLog::factory()
        ->for($employee1)
        ->forDate(now()->subDay())
        ->create(['status' => AttendanceLog::STATUS_LATE]);

    $log3 = AttendanceLog::factory()
        ->for($employee2)
        ->forDate(now())
        ->create(['status' => AttendanceLog::STATUS_PRESENT]);

    // Test forEmployee scope
    $employee1Logs = AttendanceLog::forEmployee($employee1->id)->get();
    expect($employee1Logs->count())->toBe(2);
    expect($employee1Logs->pluck('id')->toArray())->toContain($log1->id, $log2->id);

    // Test forDate scope
    $todayLogs = AttendanceLog::forDate(now())->get();
    expect($todayLogs->count())->toBe(1);
    expect($todayLogs->first()->id)->toBe($log3->id);

    // Test forStatus scope
    $presentLogs = AttendanceLog::forStatus(AttendanceLog::STATUS_PRESENT)->get();
    expect($presentLogs->count())->toBe(2);
    expect($presentLogs->pluck('id')->toArray())->toContain($log1->id, $log3->id);

    // Test forDateRange scope
    $rangeLogs = AttendanceLog::forDateRange(now()->subDays(2), now()->subDay())->get();
    expect($rangeLogs->count())->toBe(2);
    expect($rangeLogs->pluck('id')->toArray())->toContain($log1->id, $log2->id);

    // Clean up
    AttendanceLog::query()->delete();
    $employee1->user->forceDelete();
    $employee1->forceDelete();
    $employee2->user->forceDelete();
    $employee2->forceDelete();
})->group('property-test', 'hris-attendance');
