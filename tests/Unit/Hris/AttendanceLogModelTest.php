<?php

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use App\Services\Hris\PayrollPeriodService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->payrollPeriodService = new PayrollPeriodService();
});

/**
 * Generate random dates for property-based testing.
 */
function generateRandomDatesForModel(int $count): array
{
    $dates = [];
    for ($i = 0; $i < $count; $i++) {
        $year = rand(2020, 2030);
        $month = rand(1, 12);
        $maxDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $day = rand(1, $maxDay);
        $dates[] = [Carbon::create($year, $month, $day, rand(0, 23), rand(0, 59), rand(0, 59), 'Asia/Jakarta')];
    }
    return $dates;
}

/**
 * Feature: payroll-period-attendance, Property 5: Automatic period population
 * For any attendance record created with a valid date, the payroll period fields
 * (name, start, end) SHALL be automatically populated with values consistent
 * with the attendance date.
 *
 * **Validates: Requirements 2.4**
 */
describe('Property 5: Automatic period population', function () {
    it('auto-populates payroll period fields on create for any date', function (Carbon $date) {
        // Create an employee first
        $employee = Employee::factory()->create();

        // Create attendance log with the random date
        $attendanceLog = AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => $date->toDateString(),
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
            'status' => AttendanceLog::STATUS_PRESENT,
        ]);

        // Get expected period from service
        $expectedPeriod = $this->payrollPeriodService->getPeriodForDate($date);

        // Verify payroll period fields are populated
        expect($attendanceLog->payroll_period_name)->not->toBeNull()
            ->and($attendanceLog->payroll_period_start)->not->toBeNull()
            ->and($attendanceLog->payroll_period_end)->not->toBeNull();

        // Verify period name matches expected
        expect($attendanceLog->payroll_period_name)->toBe($expectedPeriod['name']);

        // Verify start date matches expected (26th of previous month)
        expect($attendanceLog->payroll_period_start->format('Y-m-d'))
            ->toBe($expectedPeriod['start_date']->format('Y-m-d'));

        // Verify end date matches expected (25th of period month)
        expect($attendanceLog->payroll_period_end->format('Y-m-d'))
            ->toBe($expectedPeriod['end_date']->format('Y-m-d'));

        // Verify the attendance date falls within the period range
        expect($date->gte($expectedPeriod['start_date']))->toBeTrue()
            ->and($date->lte($expectedPeriod['end_date']))->toBeTrue();
    })->with(generateRandomDatesForModel(100));
});

/**
 * Unit tests for AttendanceLog model payroll period functionality.
 */
describe('AttendanceLog payroll period fields', function () {
    it('does not override manually set payroll period fields', function () {
        $employee = Employee::factory()->create();

        $manualPeriodName = 'March 2025';
        $manualStartDate = '2025-02-26';
        $manualEndDate = '2025-03-25';

        $attendanceLog = AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => '2025-01-15', // This would normally be January 2025 period
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
            'status' => AttendanceLog::STATUS_PRESENT,
            'payroll_period_name' => $manualPeriodName,
            'payroll_period_start' => $manualStartDate,
            'payroll_period_end' => $manualEndDate,
        ]);

        // Verify manual values are preserved
        expect($attendanceLog->payroll_period_name)->toBe($manualPeriodName)
            ->and($attendanceLog->payroll_period_start->format('Y-m-d'))->toBe($manualStartDate)
            ->and($attendanceLog->payroll_period_end->format('Y-m-d'))->toBe($manualEndDate);
    });

    it('populates period fields for dates 1-25 with same month period', function () {
        $employee = Employee::factory()->create();

        $attendanceLog = AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => '2025-02-15',
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
            'status' => AttendanceLog::STATUS_PRESENT,
        ]);

        expect($attendanceLog->payroll_period_name)->toBe('February 2025')
            ->and($attendanceLog->payroll_period_start->format('Y-m-d'))->toBe('2025-01-26')
            ->and($attendanceLog->payroll_period_end->format('Y-m-d'))->toBe('2025-02-25');
    });

    it('populates period fields for dates 26+ with next month period', function () {
        $employee = Employee::factory()->create();

        $attendanceLog = AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => '2025-02-26',
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
            'status' => AttendanceLog::STATUS_PRESENT,
        ]);

        expect($attendanceLog->payroll_period_name)->toBe('March 2025')
            ->and($attendanceLog->payroll_period_start->format('Y-m-d'))->toBe('2025-02-26')
            ->and($attendanceLog->payroll_period_end->format('Y-m-d'))->toBe('2025-03-25');
    });

    it('handles year transition correctly (December 26+ → January next year)', function () {
        $employee = Employee::factory()->create();

        $attendanceLog = AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => '2024-12-28',
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
            'status' => AttendanceLog::STATUS_PRESENT,
        ]);

        expect($attendanceLog->payroll_period_name)->toBe('January 2025')
            ->and($attendanceLog->payroll_period_start->format('Y-m-d'))->toBe('2024-12-26')
            ->and($attendanceLog->payroll_period_end->format('Y-m-d'))->toBe('2025-01-25');
    });
});

/**
 * Unit tests for scopeForPayrollPeriod.
 */
describe('scopeForPayrollPeriod', function () {
    it('filters attendance logs by payroll period name', function () {
        $employee = Employee::factory()->create();

        // Create records in different periods
        AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => '2025-01-15',
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
            'status' => AttendanceLog::STATUS_PRESENT,
        ]);

        AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => '2025-02-15',
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
            'status' => AttendanceLog::STATUS_PRESENT,
        ]);

        AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => '2025-02-20',
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
            'status' => AttendanceLog::STATUS_PRESENT,
        ]);

        // Query for February 2025 period
        $februaryLogs = AttendanceLog::forPayrollPeriod('February 2025')->get();

        expect($februaryLogs)->toHaveCount(2);
        foreach ($februaryLogs as $log) {
            expect($log->payroll_period_name)->toBe('February 2025');
        }

        // Query for January 2025 period
        $januaryLogs = AttendanceLog::forPayrollPeriod('January 2025')->get();

        expect($januaryLogs)->toHaveCount(1);
        expect($januaryLogs->first()->payroll_period_name)->toBe('January 2025');
    });

    it('returns empty collection for non-existent period', function () {
        $employee = Employee::factory()->create();

        AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => '2025-01-15',
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
            'status' => AttendanceLog::STATUS_PRESENT,
        ]);

        $logs = AttendanceLog::forPayrollPeriod('December 2030')->get();

        expect($logs)->toHaveCount(0);
    });
});
