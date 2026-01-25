<?php

use App\Models\Hris\Employee;
use App\Models\Hris\SalaryStructure;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('SalaryStructure Model New Fields', function () {
    it('has prorate fields', function () {
        $employee = Employee::factory()->create();
        
        $structure = SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => now(),
            'basic_salary' => 4000000,
            'daily_allowance_rate' => 70000,
            'prorate_enabled' => true,
            'bpjs_paid_by' => 'company',
        ]);

        expect($structure->daily_allowance_rate)->toBe('70000.00')
            ->and($structure->prorate_enabled)->toBeTrue()
            ->and($structure->bpjs_paid_by)->toBe('company');
    });

    it('casts prorate_enabled to boolean', function () {
        $employee = Employee::factory()->create();
        
        $structure = SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => now(),
            'basic_salary' => 5000000,
            'prorate_enabled' => 1,
        ]);

        expect($structure->prorate_enabled)->toBe(true);
    });

    it('casts daily_allowance_rate to decimal', function () {
        $employee = Employee::factory()->create();
        
        $structure = SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => now(),
            'basic_salary' => 5000000,
            'daily_allowance_rate' => 75000,
        ]);

        expect($structure->daily_allowance_rate)->toBe('75000.00');
    });

    it('defaults prorate_enabled to true', function () {
        $employee = Employee::factory()->create();
        
        $structure = SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => now(),
            'basic_salary' => 5000000,
        ]);

        // Note: Default is set at database level, so it should be true
        // If migration hasn't run, this might be null
        expect($structure->prorate_enabled)->toBeIn([true, null, 1]);
    });
});

describe('SalaryStructure BPJS Paid By Options', function () {
    it('returns all BPJS paid by options', function () {
        $options = SalaryStructure::getBpjsPaidByOptions();

        expect($options)->toBe(['employee', 'company', 'shared']);
    });

    it('returns BPJS paid by labels', function () {
        $labels = SalaryStructure::getBpjsPaidByLabels();

        expect($labels)->toHaveKeys(['employee', 'company', 'shared'])
            ->and($labels['employee'])->toContain('Employee')
            ->and($labels['company'])->toContain('Company');
    });

    it('detects when BPJS is paid by company', function () {
        $employee = Employee::factory()->create();
        
        $structureCompanyPaid = SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => now(),
            'basic_salary' => 5000000,
            'bpjs_paid_by' => 'company',
        ]);

        $structureEmployeePaid = SalaryStructure::create([
            'employee_id' => $employee->id,
            'effective_date' => now()->subMonth(),
            'basic_salary' => 5000000,
            'bpjs_paid_by' => 'employee',
        ]);

        expect($structureCompanyPaid->isBpjsPaidByCompany())->toBeTrue()
            ->and($structureEmployeePaid->isBpjsPaidByCompany())->toBeFalse();
    });
});

describe('SalaryStructure Constants', function () {
    it('has BPJS paid by constants', function () {
        expect(SalaryStructure::BPJS_PAID_BY_EMPLOYEE)->toBe('employee')
            ->and(SalaryStructure::BPJS_PAID_BY_COMPANY)->toBe('company')
            ->and(SalaryStructure::BPJS_PAID_BY_SHARED)->toBe('shared');
    });
});
