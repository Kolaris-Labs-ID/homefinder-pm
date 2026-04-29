<?php

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\ClientVisit;
use App\Models\Hris\Employee;
use App\Models\Hris\MasterHoliday;
use App\Services\Hris\AttendancePayrollService;
use App\Services\Hris\PayrollSettingService;
use Carbon\Carbon;

beforeEach(function () {
    $this->settingService = new PayrollSettingService();
    $this->service = new AttendancePayrollService($this->settingService);
});

describe('AttendancePayrollService calculateWorkingDays', function () {
    it('excludes weekends from working days count', function () {
        // October 2025 has 23 working days (Mon-Fri)
        $days = $this->service->calculateWorkingDays(
            Carbon::parse('2025-10-01'),
            Carbon::parse('2025-10-31')
        );

        expect($days)->toBe(23);
    });

    it('calculates working days for partial month', function () {
        // First two weeks of October 2025: 10 working days
        $days = $this->service->calculateWorkingDays(
            Carbon::parse('2025-10-01'),
            Carbon::parse('2025-10-14')
        );

        expect($days)->toBe(10);
    });

    it('returns 0 for weekend only range', function () {
        // Saturday to Sunday
        $days = $this->service->calculateWorkingDays(
            Carbon::parse('2025-10-04'), // Saturday
            Carbon::parse('2025-10-05')  // Sunday
        );

        expect($days)->toBe(0);
    });

    it('counts single working day', function () {
        $days = $this->service->calculateWorkingDays(
            Carbon::parse('2025-10-01'), // Wednesday
            Carbon::parse('2025-10-01')
        );

        expect($days)->toBe(1);
    });
});

describe('AttendancePayrollService holiday-aware prorate helpers', function () {
    it('subtracts active weekday holidays but ignores weekend and inactive holidays', function () {
        MasterHoliday::create(['date' => '2025-10-02', 'name' => 'Weekday Holiday', 'is_active' => true]);
        MasterHoliday::create(['date' => '2025-10-04', 'name' => 'Weekend Holiday', 'is_active' => true]);
        MasterHoliday::create(['date' => '2025-10-03', 'name' => 'Inactive Holiday', 'is_active' => false]);

        expect($this->service->getEffectiveWorkingDays(10, 2025))->toBe(22);
    });

    it('counts paid holidays only inside employee hire and end dates', function () {
        MasterHoliday::create(['date' => '2025-10-02', 'name' => 'First Holiday', 'is_active' => true]);
        MasterHoliday::create(['date' => '2025-10-13', 'name' => 'Second Holiday', 'is_active' => true]);

        $activeEmployee = Employee::factory()->create([
            'hire_date' => '2025-10-01',
            'employment_end_date' => null,
            'is_active' => true,
        ]);
        $lateHire = Employee::factory()->create([
            'hire_date' => '2025-10-03',
            'employment_end_date' => null,
            'is_active' => true,
        ]);
        $endedBeforeSecondHoliday = Employee::factory()->create([
            'hire_date' => '2025-10-01',
            'employment_end_date' => '2025-10-10',
            'is_active' => true,
        ]);

        expect($this->service->getPaidHolidaysForEmployee($activeEmployee->id, 10, 2025))->toBe(2)
            ->and($this->service->getPaidHolidaysForEmployee($lateHire->id, 10, 2025))->toBe(1)
            ->and($this->service->getPaidHolidaysForEmployee($endedBeforeSecondHoliday->id, 10, 2025))->toBe(1);
    });

    it('counts distinct clock-in and visit days without requiring check-out', function () {
        $employee = Employee::factory()->create();

        AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => '2025-10-01',
            'status' => AttendanceLog::STATUS_PRESENT,
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
            'check_in_time' => Carbon::parse('2025-10-01 09:00:00'),
            'check_out_time' => null,
        ]);

        ClientVisit::create([
            'employee_id' => $employee->id,
            'client_name' => 'PT Test',
            'client_address' => 'Jakarta',
            'purpose' => ClientVisit::PURPOSE_OTHER,
            'visit_date' => '2025-10-02',
            'actual_check_in' => Carbon::parse('2025-10-02 10:00:00'),
            'status' => ClientVisit::STATUS_COMPLETED,
            'visit_type' => ClientVisit::TYPE_PLANNED,
        ]);

        ClientVisit::create([
            'employee_id' => $employee->id,
            'client_name' => 'PT Planned',
            'client_address' => 'Jakarta',
            'purpose' => ClientVisit::PURPOSE_OTHER,
            'visit_date' => '2025-10-03',
            'status' => ClientVisit::STATUS_PLANNED,
            'visit_type' => ClientVisit::TYPE_PLANNED,
        ]);

        expect($this->service->getTotalActualAttendance($employee->id, 10, 2025))->toBe(2);
    });

    it('adds virtual paid holidays to payroll paid days', function () {
        $employee = Employee::factory()->create([
            'hire_date' => '2025-10-01',
            'employment_end_date' => null,
            'is_active' => true,
        ]);

        MasterHoliday::create(['date' => '2025-10-02', 'name' => 'Paid Holiday', 'is_active' => true]);

        AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => '2025-10-01',
            'status' => AttendanceLog::STATUS_PRESENT,
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
            'check_in_time' => Carbon::parse('2025-10-01 09:00:00'),
        ]);

        $summary = $this->service->getAttendanceSummary(
            $employee,
            Carbon::parse('2025-10-01'),
            Carbon::parse('2025-10-03')
        );

        expect($summary['total_working_days'])->toBe(2)
            ->and($summary['actual_attendance_days'])->toBe(1)
            ->and($summary['eligible_paid_holiday_days'])->toBe(1)
            ->and($summary['total_paid_days'])->toBe(2)
            ->and($this->service->calculateActualDaysWorked($summary))->toBe(2.0);
    });
});

describe('AttendancePayrollService getAttendanceSummary', function () {
    it('counts attendance correctly by status', function () {
        $employee = Employee::factory()->create();
        
        // Create 15 present days
        for ($i = 1; $i <= 15; $i++) {
            AttendanceLog::create([
                'employee_id' => $employee->id,
                'date' => Carbon::parse('2025-10-01')->addDays($i - 1),
                'status' => AttendanceLog::STATUS_PRESENT,
                'mode' => AttendanceLog::MODE_OFFICE,
                'source' => AttendanceLog::SOURCE_WEB,
            ]);
        }
        
        // Create 2 late days
        for ($i = 16; $i <= 17; $i++) {
            AttendanceLog::create([
                'employee_id' => $employee->id,
                'date' => Carbon::parse('2025-10-01')->addDays($i - 1),
                'status' => AttendanceLog::STATUS_LATE,
                'mode' => AttendanceLog::MODE_OFFICE,
                'source' => AttendanceLog::SOURCE_WEB,
            ]);
        }

        $summary = $this->service->getAttendanceSummary(
            $employee,
            Carbon::parse('2025-10-01'),
            Carbon::parse('2025-10-31')
        );

        expect($summary['present_days'])->toBe(15)
            ->and($summary['late_days'])->toBe(2)
            ->and($summary['total_attendance_days'])->toBe(17);
    });

    it('counts half days', function () {
        $employee = Employee::factory()->create();
        
        AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => Carbon::parse('2025-10-01'),
            'status' => AttendanceLog::STATUS_HALF_DAY,
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
        ]);

        $summary = $this->service->getAttendanceSummary(
            $employee,
            Carbon::parse('2025-10-01'),
            Carbon::parse('2025-10-31')
        );

        expect($summary['half_days'])->toBe(1);
    });

    it('separates WFH and office days', function () {
        $employee = Employee::factory()->create();
        
        AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => Carbon::parse('2025-10-01'),
            'status' => AttendanceLog::STATUS_PRESENT,
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
        ]);
        
        AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => Carbon::parse('2025-10-02'),
            'status' => AttendanceLog::STATUS_PRESENT,
            'mode' => AttendanceLog::MODE_WFH,
            'source' => AttendanceLog::SOURCE_WEB,
        ]);

        $summary = $this->service->getAttendanceSummary(
            $employee,
            Carbon::parse('2025-10-01'),
            Carbon::parse('2025-10-31')
        );

        expect($summary['office_days'])->toBe(1)
            ->and($summary['wfh_days'])->toBe(1);
    });
});

describe('AttendancePayrollService calculateProrateRatio', function () {
    it('calculates ratio for partial month', function () {
        $ratio = $this->service->calculateProrateRatio(15, 21);

        // 15/21 = 0.7142857...
        expect($ratio)->toBeGreaterThan(0.71)
            ->and($ratio)->toBeLessThan(0.72);
    });

    it('returns 1.0 for full attendance', function () {
        $ratio = $this->service->calculateProrateRatio(21, 21);

        expect($ratio)->toBe(1.0);
    });

    it('caps ratio at 1.0 for overtime', function () {
        $ratio = $this->service->calculateProrateRatio(25, 21);

        expect($ratio)->toBe(1.0);
    });

    it('returns 1.0 when total working days is zero', function () {
        $ratio = $this->service->calculateProrateRatio(5, 0);

        expect($ratio)->toBe(1.0);
    });
});

describe('AttendancePayrollService calculateDailyAllowance', function () {
    it('calculates attendance-based daily allowance', function () {
        $config = ['type' => 'attendance_based', 'per_day_amount' => 70000];
        $attendance = ['present_days' => 15, 'late_days' => 0, 'half_days' => 0, 'wfh_days' => 0];

        $amount = $this->service->calculateDailyAllowance($config, $attendance);

        expect($amount)->toBe(1050000.00); // 15 * 70000
    });

    it('includes late days in calculation by default', function () {
        $config = ['type' => 'attendance_based', 'per_day_amount' => 70000];
        $attendance = ['present_days' => 10, 'late_days' => 5, 'half_days' => 0, 'wfh_days' => 0];

        $amount = $this->service->calculateDailyAllowance($config, $attendance);

        expect($amount)->toBe(1050000.00); // 15 * 70000
    });

    it('excludes late days when configured', function () {
        $config = ['type' => 'attendance_based', 'per_day_amount' => 70000, 'count_late' => false];
        $attendance = ['present_days' => 10, 'late_days' => 5, 'half_days' => 0, 'wfh_days' => 0];

        $amount = $this->service->calculateDailyAllowance($config, $attendance);

        expect($amount)->toBe(700000.00); // 10 * 70000
    });

    it('applies half day ratio', function () {
        $config = ['type' => 'attendance_based', 'per_day_amount' => 100000, 'half_day_calculation' => 0.5];
        $attendance = ['present_days' => 10, 'late_days' => 0, 'half_days' => 2, 'wfh_days' => 0];

        $amount = $this->service->calculateDailyAllowance($config, $attendance);

        expect($amount)->toBe(1100000.00); // (10 + 2*0.5) * 100000 = 11 * 100000
    });

    it('respects maximum cap', function () {
        $config = ['type' => 'attendance_based', 'per_day_amount' => 100000, 'max_amount' => 1500000];
        $attendance = ['present_days' => 20, 'late_days' => 0, 'half_days' => 0, 'wfh_days' => 0];

        $amount = $this->service->calculateDailyAllowance($config, $attendance);

        expect($amount)->toBe(1500000.00); // Capped at max
    });

    it('returns fixed amount for fixed type', function () {
        $config = ['type' => 'fixed', 'amount' => 500000];
        $attendance = ['present_days' => 15, 'late_days' => 0, 'half_days' => 0, 'wfh_days' => 0];

        $amount = $this->service->calculateDailyAllowance($config, $attendance);

        expect($amount)->toBe(500000.00);
    });
});

describe('AttendancePayrollService calculateProratedSalary', function () {
    it('calculates prorated salary', function () {
        $prorated = $this->service->calculateProratedSalary(4000000, 0.7143);

        // Should be approximately 2,857,200 (4M * 0.7143)
        expect($prorated)->toBeGreaterThan(2857000)
            ->and($prorated)->toBeLessThan(2858000);
    });

    it('returns full salary when ratio is 1', function () {
        $prorated = $this->service->calculateProratedSalary(4000000, 1.0);

        expect($prorated)->toBe(4000000.00);
    });
});

describe('AttendancePayrollService calculateActualDaysWorked', function () {
    it('counts present and late days by default', function () {
        $summary = ['present_days' => 10, 'late_days' => 5, 'half_days' => 0];

        $days = $this->service->calculateActualDaysWorked($summary);

        expect($days)->toBe(15.0);
    });

    it('excludes late days when configured', function () {
        $summary = ['present_days' => 10, 'late_days' => 5, 'half_days' => 0];

        $days = $this->service->calculateActualDaysWorked($summary, ['count_late' => false]);

        expect($days)->toBe(10.0);
    });

    it('applies half day ratio', function () {
        $summary = ['present_days' => 10, 'late_days' => 0, 'half_days' => 4];

        $days = $this->service->calculateActualDaysWorked($summary, ['half_day_ratio' => 0.5]);

        expect($days)->toBe(12.0); // 10 + 4*0.5
    });
});

describe('AttendancePayrollService calculateVariableAllowances', function () {
    it('calculates multiple allowances', function () {
        $allowances = [
            ['name' => 'Uang Makan', 'type' => 'attendance_based', 'per_day_amount' => 50000],
            ['name' => 'Transport', 'type' => 'attendance_based', 'per_day_amount' => 30000],
        ];
        $attendance = ['present_days' => 20, 'late_days' => 0, 'half_days' => 0, 'wfh_days' => 0];

        $result = $this->service->calculateVariableAllowances($allowances, $attendance);

        expect($result['total'])->toBe(1600000.00) // (50000 + 30000) * 20
            ->and($result['breakdown'])->toHaveCount(2);
    });
});

describe('AttendancePayrollService getAttendanceLabel', function () {
    it('returns formatted attendance label', function () {
        $summary = ['present_days' => 15, 'late_days' => 2, 'total_working_days' => 21];

        $label = $this->service->getAttendanceLabel($summary);

        expect($label)->toBe('17/21 HK');
    });
});
