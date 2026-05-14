<?php

use App\Models\Hris\Employee;
use App\Models\Hris\LeaveBalance;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\LeaveType;
use App\Services\Hris\LeaveBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->employee = Employee::factory()->create([
        'hire_date' => now()->subYears(2),
    ]);

    // The UNPAID and ANNUAL types are seeded by a data migration; use firstOrCreate
    // so tests are resilient whether or not those rows already exist.
    $this->annualType = LeaveType::firstOrCreate(
        ['code' => 'ANNUAL'],
        [
            'name' => 'Annual Leave',
            'default_annual_quota' => 12,
            'requires_approval' => true,
            'requires_attachment' => false,
            'carry_forward_allowed' => false,
            'is_special' => false,
            'is_active' => true,
        ]
    );

    $this->unpaidType = LeaveType::firstOrCreate(
        ['code' => 'UNPAID'],
        [
            'name' => 'Unpaid Leave',
            'default_annual_quota' => 0,
            'requires_approval' => true,
            'requires_attachment' => false,
            'carry_forward_allowed' => false,
            'is_special' => false,
            'is_active' => true,
        ]
    );

    app(LeaveBalanceService::class)->ensureBalance($this->employee, $this->annualType, now()->year);
});

it('rejects unpaid leave when annual balance still remains', function () {
    Sanctum::actingAs($this->employee->user);

    $response = postJson('/api/hris/leave-requests', [
        'leave_type_id' => $this->unpaidType->id,
        'start_date' => now()->addDays(2)->toDateString(),
        'end_date' => now()->addDays(3)->toDateString(),
        'reason' => 'Need extra time off',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['leave_type_id']);

    expect($response->json('errors.leave_type_id.0'))
        ->toContain('Unpaid Leave is only available after your annual leave quota is exhausted');

    expect(LeaveRequest::count())->toBe(0);
});

it('allows unpaid leave when annual balance is exhausted', function () {
    // Drain annual balance.
    $annualBalance = $this->employee->leaveBalances()
        ->where('leave_type_id', $this->annualType->id)
        ->first();
    $annualBalance->update([
        'used_days' => $annualBalance->starting_balance,
        'remaining_days' => 0,
    ]);

    Sanctum::actingAs($this->employee->user);

    $response = postJson('/api/hris/leave-requests', [
        'leave_type_id' => $this->unpaidType->id,
        'start_date' => now()->addDays(2)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
        'reason' => 'Need extra time off',
    ]);

    $response->assertCreated();

    $leaveRequest = LeaveRequest::first();
    expect($leaveRequest)->not->toBeNull();
    expect($leaveRequest->leave_type_id)->toBe($this->unpaidType->id);
    expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_PENDING);
});

it('allows unpaid leave when employee has no annual balance yet', function () {
    LeaveBalance::query()
        ->where('employee_id', $this->employee->id)
        ->where('leave_type_id', $this->annualType->id)
        ->where('year', now()->year)
        ->delete();

    Sanctum::actingAs($this->employee->user);

    $response = postJson('/api/hris/leave-requests', [
        'leave_type_id' => $this->unpaidType->id,
        'start_date' => now()->addDays(2)->toDateString(),
        'end_date' => now()->addDays(3)->toDateString(),
        'reason' => 'Need unpaid leave before annual quota exists',
    ]);

    $response->assertCreated();

    expect(LeaveRequest::query()->where('leave_type_id', $this->unpaidType->id)->exists())->toBeTrue();

    $annualBalanceExists = LeaveBalance::query()
        ->where('employee_id', $this->employee->id)
        ->where('leave_type_id', $this->annualType->id)
        ->where('year', now()->year)
        ->exists();

    expect($annualBalanceExists)->toBeFalse();
});

it('does not create a balance row when approving unpaid leave', function () {
    // Drain annual balance.
    $annualBalance = $this->employee->leaveBalances()
        ->where('leave_type_id', $this->annualType->id)
        ->first();
    $annualBalance->update([
        'used_days' => $annualBalance->starting_balance,
        'remaining_days' => 0,
    ]);

    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->unpaidType->id,
        'status' => LeaveRequest::STATUS_PENDING,
        'start_date' => now()->addDays(2),
        'end_date' => now()->addDays(3),
        'total_days' => 2,
    ]);

    app(LeaveBalanceService::class)->consume($leaveRequest);

    // No LeaveBalance row should exist for UNPAID.
    $exists = LeaveBalance::query()
        ->where('employee_id', $this->employee->id)
        ->where('leave_type_id', $this->unpaidType->id)
        ->exists();

    expect($exists)->toBeFalse();
});

it('release is a no-op for unpaid leave', function () {
    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->unpaidType->id,
        'status' => LeaveRequest::STATUS_APPROVED,
        'start_date' => now()->addDays(2),
        'end_date' => now()->addDays(3),
        'total_days' => 2,
    ]);

    app(LeaveBalanceService::class)->release($leaveRequest);

    $exists = LeaveBalance::query()
        ->where('employee_id', $this->employee->id)
        ->where('leave_type_id', $this->unpaidType->id)
        ->exists();

    expect($exists)->toBeFalse();
});
