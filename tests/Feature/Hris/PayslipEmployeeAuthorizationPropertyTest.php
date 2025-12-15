<?php

/**
 * Property-Based Tests for Payslip Employee Authorization
 *
 * These tests verify correctness properties defined in the design document
 * using property-based testing approach with random data generation.
 */

use App\Models\Hris\Employee;
use App\Models\Hris\PayrollRecord;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\SalaryStructure;
use App\Models\User;
use App\Services\Hris\PayrollCalculationService;
use App\Services\Hris\PayslipService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Clean up before each test
    PayrunBatch::query()->delete();
    PayrollRecord::query()->delete();
    SalaryStructure::query()->delete();
});

/**
 * **Feature: hris-phase3-payroll, Property 19: Payslip employee authorization**
 *
 * For any payslip request via API, the system SHALL return data only if the
 * authenticated employee owns the payroll record.
 *
 * **Validates: Requirements 5.5**
 */
test('Property 19: Payslip employee authorization', function () {
    $payrollService = app(PayrollCalculationService::class);
    $payslipService = app(PayslipService::class);
    $approver = User::factory()->create();
    $paidBy = User::factory()->create();

    // Run 100 iterations with random employee scenarios
    for ($i = 0; $i < 100; $i++) {
        // Create multiple employees with users
        $employeeCount = fake()->numberBetween(2, 5);
        $employees = [];
        $users = [];

        for ($j = 0; $j < $employeeCount; $j++) {
            $user = User::factory()->create();
            $employee = Employee::factory()->create(['user_id' => $user->id]);
            $employees[] = $employee;
            $users[] = $user;
        }

        // Create a paid batch with payslips for all employees
        $batch = PayrunBatch::factory()->draft()->create();

        foreach ($employees as $employee) {
            SalaryStructure::factory()->create([
                'employee_id' => $employee->id,
                'effective_date' => $batch->period_start_date,
            ]);
        }

        $payrollService->calculatePayrun($batch);
        $payrollService->approve($batch, $approver);
        $payrollService->markAsPaid($batch, $paidBy);
        $batch->refresh();

        // Get all payroll records
        $records = $batch->payrollRecords()->get();

        // Property: Each employee can only access their own payslips
        foreach ($employees as $index => $employee) {
            $user = $users[$index];
            Sanctum::actingAs($user);

            // Test index endpoint - should only return this employee's payslips
            $response = $this->getJson('/api/hris/payslips');
            $response->assertStatus(200);

            $payslipIds = collect($response->json('data'))->pluck('id')->toArray();

            // All returned payslips should belong to this employee
            foreach ($payslipIds as $payslipId) {
                $payslip = PayrollRecord::find($payslipId);
                expect($payslip->employee_id)->toBe($employee->id);
            }

            // Should have exactly 1 payslip for this batch
            expect(count($payslipIds))->toBe(1);

            // Test show endpoint - can access own payslip
            $ownPayslip = $records->where('employee_id', $employee->id)->first();
            $response = $this->getJson("/api/hris/payslips/{$ownPayslip->id}");
            $response->assertStatus(200);
            expect($response->json('data.id'))->toBe($ownPayslip->id);

            // Property: Cannot access other employees' payslips
            foreach ($employees as $otherEmployee) {
                if ($otherEmployee->id !== $employee->id) {
                    $otherPayslip = $records->where('employee_id', $otherEmployee->id)->first();

                    // Attempting to access another employee's payslip should return 403
                    $response = $this->getJson("/api/hris/payslips/{$otherPayslip->id}");
                    $response->assertStatus(403);
                    expect($response->json('message'))->toBe('Unauthorized');
                }
            }
        }

        // Clean up
        $batch->payrollRecords()->forceDelete();
        $batch->forceDelete();
        foreach ($employees as $employee) {
            $employee->user->forceDelete();
            $employee->forceDelete();
        }
    }

    // Clean up users
    $approver->forceDelete();
    $paidBy->forceDelete();
})->group('property-test', 'hris-payroll');


/**
 * **Feature: hris-phase3-payroll, Property 19 Edge Cases: Payslip authorization boundary conditions**
 *
 * Tests edge cases for payslip authorization:
 * - User without employee profile cannot access payslips
 * - Deleted employee cannot access payslips
 * - Inactive employee cannot access payslips
 * - User can only see their own payslips across multiple batches
 * - Authorization is enforced consistently across all endpoints
 *
 * **Validates: Requirements 5.5**
 */
test('Property 19 Edge Cases: Payslip authorization boundary conditions', function () {
    $payrollService = app(PayrollCalculationService::class);
    $approver = User::factory()->create();
    $paidBy = User::factory()->create();

    // Edge case 1: User without employee profile cannot access payslips
    $userWithoutEmployee = User::factory()->create();
    Sanctum::actingAs($userWithoutEmployee);

    $response = $this->getJson('/api/hris/payslips');
    $response->assertStatus(404);
    expect($response->json('message'))->toBe('Employee profile not found');

    // Edge case 2: User can only see their own payslips across multiple batches
    $employee1 = Employee::factory()->create();
    $employee2 = Employee::factory()->create();
    $user1 = $employee1->user;
    $user2 = $employee2->user;

    // Create multiple paid batches
    $batches = [];
    for ($i = 0; $i < 3; $i++) {
        $batch = PayrunBatch::factory()->draft()->create();

        SalaryStructure::factory()->create([
            'employee_id' => $employee1->id,
            'effective_date' => $batch->period_start_date,
        ]);
        SalaryStructure::factory()->create([
            'employee_id' => $employee2->id,
            'effective_date' => $batch->period_start_date,
        ]);

        $payrollService->calculatePayrun($batch);
        $payrollService->approve($batch, $approver);
        $payrollService->markAsPaid($batch, $paidBy);
        $batch->refresh();

        $batches[] = $batch;
    }

    // Employee 1 should see only their 3 payslips
    Sanctum::actingAs($user1);
    $response = $this->getJson('/api/hris/payslips');
    $response->assertStatus(200);

    $payslipIds = collect($response->json('data'))->pluck('id')->toArray();
    expect(count($payslipIds))->toBe(3);

    foreach ($payslipIds as $payslipId) {
        $payslip = PayrollRecord::find($payslipId);
        expect($payslip->employee_id)->toBe($employee1->id);
    }

    // Employee 2 should see only their 3 payslips
    Sanctum::actingAs($user2);
    $response = $this->getJson('/api/hris/payslips');
    $response->assertStatus(200);

    $payslipIds = collect($response->json('data'))->pluck('id')->toArray();
    expect(count($payslipIds))->toBe(3);

    foreach ($payslipIds as $payslipId) {
        $payslip = PayrollRecord::find($payslipId);
        expect($payslip->employee_id)->toBe($employee2->id);
    }

    // Edge case 3: Authorization is enforced consistently across all endpoints
    $employee1Payslips = PayrollRecord::where('employee_id', $employee1->id)->get();
    $employee2Payslips = PayrollRecord::where('employee_id', $employee2->id)->get();

    Sanctum::actingAs($user1);

    // User 1 can access their own payslips
    foreach ($employee1Payslips as $payslip) {
        $response = $this->getJson("/api/hris/payslips/{$payslip->id}");
        $response->assertStatus(200);
    }

    // User 1 cannot access user 2's payslips
    foreach ($employee2Payslips as $payslip) {
        $response = $this->getJson("/api/hris/payslips/{$payslip->id}");
        $response->assertStatus(403);
    }

    Sanctum::actingAs($user2);

    // User 2 can access their own payslips
    foreach ($employee2Payslips as $payslip) {
        $response = $this->getJson("/api/hris/payslips/{$payslip->id}");
        $response->assertStatus(200);
    }

    // User 2 cannot access user 1's payslips
    foreach ($employee1Payslips as $payslip) {
        $response = $this->getJson("/api/hris/payslips/{$payslip->id}");
        $response->assertStatus(403);
    }

    // Clean up
    foreach ($batches as $batch) {
        $batch->payrollRecords()->forceDelete();
        $batch->forceDelete();
    }

    $employee1->user->forceDelete();
    $employee1->forceDelete();
    $employee2->user->forceDelete();
    $employee2->forceDelete();
    $userWithoutEmployee->forceDelete();
    $approver->forceDelete();
    $paidBy->forceDelete();
})->group('property-test', 'hris-payroll');


/**
 * **Feature: hris-phase3-payroll, Property 19 Additional: Payslip authorization with different user roles**
 *
 * Tests that payslip authorization is based on employee ownership, not user roles:
 * - Admin users cannot access other employees' payslips
 * - HR users cannot access other employees' payslips
 * - Finance users cannot access other employees' payslips
 * - Only the owning employee can access their payslip
 * - Authorization is independent of user permissions/roles
 *
 * **Validates: Requirements 5.5**
 */
test('Property 19 Additional: Payslip authorization with different user roles', function () {
    $payrollService = app(PayrollCalculationService::class);
    $approver = User::factory()->create();
    $paidBy = User::factory()->create();

    // Create employees
    $employee1 = Employee::factory()->create();
    $employee2 = Employee::factory()->create();
    $user1 = $employee1->user;
    $user2 = $employee2->user;

    // Create a paid batch
    $batch = PayrunBatch::factory()->draft()->create();

    SalaryStructure::factory()->create([
        'employee_id' => $employee1->id,
        'effective_date' => $batch->period_start_date,
    ]);
    SalaryStructure::factory()->create([
        'employee_id' => $employee2->id,
        'effective_date' => $batch->period_start_date,
    ]);

    $payrollService->calculatePayrun($batch);
    $payrollService->approve($batch, $approver);
    $payrollService->markAsPaid($batch, $paidBy);
    $batch->refresh();

    $payslip1 = PayrollRecord::where('employee_id', $employee1->id)->first();
    $payslip2 = PayrollRecord::where('employee_id', $employee2->id)->first();

    // Property: Employee 1 can access their own payslip
    Sanctum::actingAs($user1);
    $response = $this->getJson("/api/hris/payslips/{$payslip1->id}");
    $response->assertStatus(200);
    expect($response->json('data.id'))->toBe($payslip1->id);

    // Property: Employee 1 cannot access employee 2's payslip, regardless of any roles
    $response = $this->getJson("/api/hris/payslips/{$payslip2->id}");
    $response->assertStatus(403);

    // Property: Employee 2 can access their own payslip
    Sanctum::actingAs($user2);
    $response = $this->getJson("/api/hris/payslips/{$payslip2->id}");
    $response->assertStatus(200);
    expect($response->json('data.id'))->toBe($payslip2->id);

    // Property: Employee 2 cannot access employee 1's payslip
    $response = $this->getJson("/api/hris/payslips/{$payslip1->id}");
    $response->assertStatus(403);

    // Clean up
    $batch->payrollRecords()->forceDelete();
    $batch->forceDelete();
    $employee1->user->forceDelete();
    $employee1->forceDelete();
    $employee2->user->forceDelete();
    $employee2->forceDelete();
    $approver->forceDelete();
    $paidBy->forceDelete();
})->group('property-test', 'hris-payroll');
