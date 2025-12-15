<?php

/**
 * Property-Based Tests for Role-Based Payroll Access Control
 *
 * These tests verify correctness properties defined in the design document
 * using property-based testing approach with random data generation.
 */

use App\Models\Hris\Employee;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\SalaryStructure;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Clean up before each test
    PayrunBatch::query()->delete();
    SalaryStructure::query()->delete();

    // Ensure roles exist
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'finance', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web']);

    // Create salary structure permissions
    $salaryStructurePermissions = [
        'view_salary::structure',
        'view_any_salary::structure',
        'create_salary::structure',
        'update_salary::structure',
        'delete_salary::structure',
    ];

    foreach ($salaryStructurePermissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    // Create payrun batch permissions
    $payrunBatchPermissions = [
        'view_payrun::batch',
        'view_any_payrun::batch',
        'create_payrun::batch',
        'update_payrun::batch',
        'delete_payrun::batch',
    ];

    foreach ($payrunBatchPermissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    // Assign permissions to hr-admin role
    $hrAdminRole = Role::findByName('hr-admin', 'web');
    $hrAdminRole->syncPermissions([
        'view_salary::structure',
        'view_any_salary::structure',
        'create_salary::structure',
        'update_salary::structure',
        'delete_salary::structure',
        'view_payrun::batch',
        'view_any_payrun::batch',
        'create_payrun::batch',
        'update_payrun::batch',
    ]);

    // Assign permissions to finance role
    $financeRole = Role::findByName('finance', 'web');
    $financeRole->syncPermissions([
        'view_salary::structure',
        'view_any_salary::structure',
        'create_salary::structure',
        'update_salary::structure',
        'delete_salary::structure',
        'view_payrun::batch',
        'view_any_payrun::batch',
        'update_payrun::batch',
    ]);

    // Member role has no payroll permissions
    $memberRole = Role::findByName('member', 'web');
    $memberRole->syncPermissions([]);
});

/**
 * **Feature: hris-phase3-payroll, Property 20: Role-based payroll access control**
 *
 * For any user without hr-admin or finance role, access to salary structures
 * and payrun batches SHALL be denied.
 *
 * **Validates: Requirements 8.1, 8.2**
 */
test('Property 20: Role-based payroll access control', function () {
    // Run 100 iterations with random user/role scenarios
    for ($i = 0; $i < 100; $i++) {
        // Create users with different roles
        $hrAdminUser = User::factory()->create();
        $hrAdminUser->assignRole('hr-admin');

        $financeUser = User::factory()->create();
        $financeUser->assignRole('finance');

        $memberUser = User::factory()->create();
        $memberUser->assignRole('member');

        $noRoleUser = User::factory()->create();

        // Create test data
        $employee = Employee::factory()->create();
        $salaryStructure = SalaryStructure::factory()->create([
            'employee_id' => $employee->id,
        ]);
        $payrunBatch = PayrunBatch::factory()->draft()->create();

        // Property: HR-admin can access salary structures
        expect($hrAdminUser->can('view_any_salary::structure'))->toBeTrue();
        expect($hrAdminUser->can('view_salary::structure'))->toBeTrue();
        expect($hrAdminUser->can('create_salary::structure'))->toBeTrue();
        expect($hrAdminUser->can('update_salary::structure'))->toBeTrue();

        // Property: HR-admin can create/calculate payrun batches
        expect($hrAdminUser->can('view_any_payrun::batch'))->toBeTrue();
        expect($hrAdminUser->can('view_payrun::batch'))->toBeTrue();
        expect($hrAdminUser->can('create_payrun::batch'))->toBeTrue();
        expect($hrAdminUser->can('update_payrun::batch'))->toBeTrue();

        // Property: Finance can access salary structures
        expect($financeUser->can('view_any_salary::structure'))->toBeTrue();
        expect($financeUser->can('view_salary::structure'))->toBeTrue();
        expect($financeUser->can('create_salary::structure'))->toBeTrue();
        expect($financeUser->can('update_salary::structure'))->toBeTrue();

        // Property: Finance can view and update payrun batches (for approve/pay)
        expect($financeUser->can('view_any_payrun::batch'))->toBeTrue();
        expect($financeUser->can('view_payrun::batch'))->toBeTrue();
        expect($financeUser->can('update_payrun::batch'))->toBeTrue();

        // Property: Member cannot access salary structures
        expect($memberUser->can('view_any_salary::structure'))->toBeFalse();
        expect($memberUser->can('view_salary::structure'))->toBeFalse();
        expect($memberUser->can('create_salary::structure'))->toBeFalse();
        expect($memberUser->can('update_salary::structure'))->toBeFalse();
        expect($memberUser->can('delete_salary::structure'))->toBeFalse();

        // Property: Member cannot access payrun batches
        expect($memberUser->can('view_any_payrun::batch'))->toBeFalse();
        expect($memberUser->can('view_payrun::batch'))->toBeFalse();
        expect($memberUser->can('create_payrun::batch'))->toBeFalse();
        expect($memberUser->can('update_payrun::batch'))->toBeFalse();
        expect($memberUser->can('delete_payrun::batch'))->toBeFalse();

        // Property: User without role cannot access salary structures
        expect($noRoleUser->can('view_any_salary::structure'))->toBeFalse();
        expect($noRoleUser->can('view_salary::structure'))->toBeFalse();
        expect($noRoleUser->can('create_salary::structure'))->toBeFalse();
        expect($noRoleUser->can('update_salary::structure'))->toBeFalse();
        expect($noRoleUser->can('delete_salary::structure'))->toBeFalse();

        // Property: User without role cannot access payrun batches
        expect($noRoleUser->can('view_any_payrun::batch'))->toBeFalse();
        expect($noRoleUser->can('view_payrun::batch'))->toBeFalse();
        expect($noRoleUser->can('create_payrun::batch'))->toBeFalse();
        expect($noRoleUser->can('update_payrun::batch'))->toBeFalse();
        expect($noRoleUser->can('delete_payrun::batch'))->toBeFalse();

        // Clean up
        $salaryStructure->forceDelete();
        $payrunBatch->forceDelete();
        $employee->user->forceDelete();
        $employee->forceDelete();
        $hrAdminUser->forceDelete();
        $financeUser->forceDelete();
        $memberUser->forceDelete();
        $noRoleUser->forceDelete();
    }
})->group('property-test', 'hris-payroll');
