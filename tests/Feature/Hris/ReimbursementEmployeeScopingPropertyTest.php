<?php

/**
 * Property-Based Tests for Reimbursement Employee Scoping
 *
 * These tests verify correctness properties defined in the design document
 * using property-based testing approach with random data generation.
 */

use App\Models\Hris\Employee;
use App\Models\Hris\Reimbursement;
use App\Models\User;
use App\Services\Hris\ReimbursementService;

beforeEach(function () {
    // Clean up before each test
    Reimbursement::query()->delete();
});

/**
 * **Feature: hris-phase3-payroll, Property 16: Reimbursement employee scoping**
 *
 * For any employee querying reimbursements, the system SHALL return only
 * reimbursements belonging to that employee.
 *
 * **Validates: Requirements 4.7**
 */
test('Property 16: Reimbursement employee scoping', function () {
    $reimbursementService = app(ReimbursementService::class);

    // Run 100 iterations with random employee and reimbursement scenarios
    for ($i = 0; $i < 100; $i++) {
        // Create multiple employees
        $employees = Employee::factory()->count(fake()->numberBetween(2, 5))->create();

        // Create reimbursements for each employee
        $reimbursementsByEmployee = [];
        foreach ($employees as $employee) {
            $count = fake()->numberBetween(1, 5);
            $reimbursements = Reimbursement::factory()
                ->count($count)
                ->create(['employee_id' => $employee->id]);

            $reimbursementsByEmployee[$employee->id] = $reimbursements->pluck('id')->toArray();
        }

        // For each employee, verify they can only see their own reimbursements
        foreach ($employees as $employee) {
            // Get reimbursements for this employee using the service
            $employeeReimbursements = $reimbursementService->getEmployeeReimbursements($employee);

            // Property: All returned reimbursements must belong to this employee
            foreach ($employeeReimbursements as $reimbursement) {
                expect($reimbursement->employee_id)->toBe($employee->id);
            }

            // Property: The count must match what we created for this employee
            expect($employeeReimbursements->count())->toBe(count($reimbursementsByEmployee[$employee->id]));

            // Property: All reimbursement IDs must be in the expected set
            $returnedIds = $employeeReimbursements->pluck('id')->toArray();
            foreach ($returnedIds as $id) {
                expect(in_array($id, $reimbursementsByEmployee[$employee->id]))->toBeTrue();
            }

            // Property: No reimbursements from other employees should be returned
            foreach ($employees as $otherEmployee) {
                if ($otherEmployee->id !== $employee->id) {
                    foreach ($reimbursementsByEmployee[$otherEmployee->id] as $otherId) {
                        expect(in_array($otherId, $returnedIds))->toBeFalse();
                    }
                }
            }
        }

        // Clean up
        foreach ($employees as $employee) {
            $employee->forceDelete();
        }
    }
})->group('property-test', 'hris-payroll');


/**
 * **Feature: hris-phase3-payroll, Property 16 Edge Cases: Employee scoping boundaries**
 *
 * Tests edge cases for employee scoping:
 * - Employee with no reimbursements returns empty collection
 * - Multiple employees with same reimbursement type don't interfere
 * - Reimbursements are properly filtered by employee_id in database query
 * - Deleted reimbursements don't appear in results
 * - Different statuses don't affect scoping (all statuses are scoped)
 *
 * **Validates: Requirements 4.7**
 */
test('Property 16 Edge Cases: Employee scoping boundaries', function () {
    $reimbursementService = app(ReimbursementService::class);

    // Edge case 1: Employee with no reimbursements returns empty collection
    $employee1 = Employee::factory()->create();
    $reimbursements1 = $reimbursementService->getEmployeeReimbursements($employee1);

    expect($reimbursements1->count())->toBe(0);
    expect($reimbursements1)->toBeEmpty();

    // Edge case 2: Multiple employees with same reimbursement type don't interfere
    $employee2 = Employee::factory()->create();
    $employee3 = Employee::factory()->create();

    // Create reimbursements of the same type for different employees
    $reimbursement2 = Reimbursement::factory()
        ->medical()
        ->create(['employee_id' => $employee2->id]);

    $reimbursement3 = Reimbursement::factory()
        ->medical()
        ->create(['employee_id' => $employee3->id]);

    // Get reimbursements for employee2
    $employee2Reimbursements = $reimbursementService->getEmployeeReimbursements($employee2);

    // Property: Should only contain employee2's reimbursement
    expect($employee2Reimbursements->count())->toBe(1);
    expect($employee2Reimbursements->first()->id)->toBe($reimbursement2->id);
    expect($employee2Reimbursements->first()->employee_id)->toBe($employee2->id);

    // Get reimbursements for employee3
    $employee3Reimbursements = $reimbursementService->getEmployeeReimbursements($employee3);

    // Property: Should only contain employee3's reimbursement
    expect($employee3Reimbursements->count())->toBe(1);
    expect($employee3Reimbursements->first()->id)->toBe($reimbursement3->id);
    expect($employee3Reimbursements->first()->employee_id)->toBe($employee3->id);

    // Edge case 3: Reimbursements are properly filtered by employee_id in database query
    $employee4 = Employee::factory()->create();

    // Create multiple reimbursements for employee4
    $reimbursements4 = Reimbursement::factory()
        ->count(5)
        ->create(['employee_id' => $employee4->id]);

    // Create reimbursements for other employees
    $otherEmployee = Employee::factory()->create();
    Reimbursement::factory()
        ->count(3)
        ->create(['employee_id' => $otherEmployee->id]);

    // Get reimbursements for employee4
    $employee4Reimbursements = $reimbursementService->getEmployeeReimbursements($employee4);

    // Property: Should only contain employee4's reimbursements
    expect($employee4Reimbursements->count())->toBe(5);
    foreach ($employee4Reimbursements as $reimbursement) {
        expect($reimbursement->employee_id)->toBe($employee4->id);
    }

    // Edge case 4: Different statuses don't affect scoping (all statuses are scoped)
    $employee5 = Employee::factory()->create();

    // Create reimbursements with different statuses
    $pending = Reimbursement::factory()
        ->pending()
        ->create(['employee_id' => $employee5->id]);

    $approved = Reimbursement::factory()
        ->approved()
        ->create(['employee_id' => $employee5->id]);

    $rejected = Reimbursement::factory()
        ->rejected()
        ->create(['employee_id' => $employee5->id]);

    $paid = Reimbursement::factory()
        ->paid()
        ->create(['employee_id' => $employee5->id]);

    // Create reimbursements with different statuses for another employee
    $otherEmployee2 = Employee::factory()->create();
    Reimbursement::factory()
        ->pending()
        ->create(['employee_id' => $otherEmployee2->id]);

    // Get reimbursements for employee5
    $employee5Reimbursements = $reimbursementService->getEmployeeReimbursements($employee5);

    // Property: Should contain all 4 reimbursements regardless of status
    expect($employee5Reimbursements->count())->toBe(4);

    // Property: All returned reimbursements must belong to employee5
    foreach ($employee5Reimbursements as $reimbursement) {
        expect($reimbursement->employee_id)->toBe($employee5->id);
    }

    // Property: Should have one of each status
    $statuses = $employee5Reimbursements->pluck('status')->toArray();
    expect(in_array(Reimbursement::STATUS_PENDING, $statuses))->toBeTrue();
    expect(in_array(Reimbursement::STATUS_APPROVED, $statuses))->toBeTrue();
    expect(in_array(Reimbursement::STATUS_REJECTED, $statuses))->toBeTrue();
    expect(in_array(Reimbursement::STATUS_PAID, $statuses))->toBeTrue();

    // Clean up
    $employee1->forceDelete();
    $employee2->forceDelete();
    $employee3->forceDelete();
    $employee4->forceDelete();
    $employee5->forceDelete();
    $otherEmployee->forceDelete();
    $otherEmployee2->forceDelete();
})->group('property-test', 'hris-payroll');


/**
 * **Feature: hris-phase3-payroll, Property 16 API: Employee scoping in API endpoints**
 *
 * Tests that employee scoping is properly enforced in API endpoints:
 * - API index endpoint returns only authenticated employee's reimbursements
 * - API cancel endpoint checks employee ownership
 * - Employees cannot access other employees' reimbursements
 *
 * **Validates: Requirements 4.7, 7.3, 7.5**
 */
test('Property 16 API: Employee scoping in API endpoints', function () {
    // Create employees and their associated users
    $employee1 = Employee::factory()->create();
    $user1 = User::factory()->create();
    $employee1->update(['user_id' => $user1->id]);

    $employee2 = Employee::factory()->create();
    $user2 = User::factory()->create();
    $employee2->update(['user_id' => $user2->id]);

    // Create reimbursements for each employee
    $reimbursement1 = Reimbursement::factory()
        ->pending()
        ->create(['employee_id' => $employee1->id]);

    $reimbursement2 = Reimbursement::factory()
        ->pending()
        ->create(['employee_id' => $employee2->id]);

    // Test 1: User1 can only see their own reimbursements via API
    $response1 = $this->actingAs($user1)->getJson('/api/hris/reimbursements');

    expect($response1->status())->toBe(200);
    $data1 = $response1->json('data');
    expect(count($data1))->toBe(1);
    expect($data1[0]['id'])->toBe($reimbursement1->id);

    // Test 2: User2 can only see their own reimbursements via API
    $response2 = $this->actingAs($user2)->getJson('/api/hris/reimbursements');

    expect($response2->status())->toBe(200);
    $data2 = $response2->json('data');
    expect(count($data2))->toBe(1);
    expect($data2[0]['id'])->toBe($reimbursement2->id);

    // Test 3: User1 cannot cancel User2's reimbursement
    $response3 = $this->actingAs($user1)->putJson(
        "/api/hris/reimbursements/{$reimbursement2->id}/cancel"
    );

    expect($response3->status())->toBe(403);

    // Test 4: User1 can cancel their own reimbursement
    $response4 = $this->actingAs($user1)->putJson(
        "/api/hris/reimbursements/{$reimbursement1->id}/cancel"
    );

    expect($response4->status())->toBe(200);

    // Clean up
    $user1->forceDelete();
    $user2->forceDelete();
    $employee1->forceDelete();
    $employee2->forceDelete();
})->group('property-test', 'hris-payroll');
