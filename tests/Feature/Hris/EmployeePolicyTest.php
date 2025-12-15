<?php

use App\Models\Hris\Employee;
use App\Models\User;
use App\Policies\Hris\EmployeePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->policy = new EmployeePolicy();
    $this->employee = Employee::factory()->create();

    // Create permissions used by the policy
    Permission::create(['name' => 'view_any_employee', 'guard_name' => 'web']);
    Permission::create(['name' => 'view_employee', 'guard_name' => 'web']);
    Permission::create(['name' => 'create_employee', 'guard_name' => 'web']);
    Permission::create(['name' => 'update_employee', 'guard_name' => 'web']);
    Permission::create(['name' => 'delete_employee', 'guard_name' => 'web']);
});

test('hr-admin can view any employee', function () {
    $hrAdmin = User::factory()->create();
    $role = Role::create(['name' => 'hr-admin', 'guard_name' => 'web']);
    $role->givePermissionTo('view_any_employee');
    $hrAdmin->assignRole($role);

    expect($this->policy->viewAny($hrAdmin))->toBeTrue();
});

test('hr-staff can view any employee', function () {
    $hrStaff = User::factory()->create();
    $role = Role::create(['name' => 'hr-staff', 'guard_name' => 'web']);
    $role->givePermissionTo('view_any_employee');
    $hrStaff->assignRole($role);

    expect($this->policy->viewAny($hrStaff))->toBeTrue();
});

test('super_admin can view any employee', function () {
    $superAdmin = User::factory()->create();
    $role = Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    $role->givePermissionTo('view_any_employee');
    $superAdmin->assignRole($role);

    expect($this->policy->viewAny($superAdmin))->toBeTrue();
});

test('regular user cannot view any employee', function () {
    $user = User::factory()->create();

    expect($this->policy->viewAny($user))->toBeFalse();
});

test('hr-admin can create employee', function () {
    $hrAdmin = User::factory()->create();
    $role = Role::create(['name' => 'hr-admin', 'guard_name' => 'web']);
    $role->givePermissionTo('create_employee');
    $hrAdmin->assignRole($role);

    expect($this->policy->create($hrAdmin))->toBeTrue();
});

test('regular user cannot create employee', function () {
    $user = User::factory()->create();

    expect($this->policy->create($user))->toBeFalse();
});

test('employee can view their own profile', function () {
    $user = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    // Give user permission to view employee
    $user->givePermissionTo('view_employee');

    expect($this->policy->view($user, $employee))->toBeTrue();
});

test('hr-admin can view any employee profile', function () {
    $hrAdmin = User::factory()->create();
    $role = Role::create(['name' => 'hr-admin', 'guard_name' => 'web']);
    $role->givePermissionTo('view_employee');
    $hrAdmin->assignRole($role);

    expect($this->policy->view($hrAdmin, $this->employee))->toBeTrue();
});

test('regular user cannot view other employee profiles', function () {
    $user = User::factory()->create();
    $otherEmployee = Employee::factory()->create();

    expect($this->policy->view($user, $otherEmployee))->toBeFalse();
});

test('hr-admin can update any employee', function () {
    $hrAdmin = User::factory()->create();
    $role = Role::create(['name' => 'hr-admin', 'guard_name' => 'web']);
    $role->givePermissionTo('update_employee');
    $hrAdmin->assignRole($role);

    expect($this->policy->update($hrAdmin, $this->employee))->toBeTrue();
});

test('employee can update their own profile', function () {
    $user = User::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    // Give user permission to update employee
    $user->givePermissionTo('update_employee');

    expect($this->policy->update($user, $employee))->toBeTrue();
});

test('regular user cannot update other employees', function () {
    $user = User::factory()->create();

    expect($this->policy->update($user, $this->employee))->toBeFalse();
});

test('hr-admin can delete employee', function () {
    $hrAdmin = User::factory()->create();
    $role = Role::create(['name' => 'hr-admin', 'guard_name' => 'web']);
    $role->givePermissionTo('delete_employee');
    $hrAdmin->assignRole($role);

    expect($this->policy->delete($hrAdmin, $this->employee))->toBeTrue();
});

test('regular user cannot delete employee', function () {
    $user = User::factory()->create();

    expect($this->policy->delete($user, $this->employee))->toBeFalse();
});

