<?php

use App\Models\Hris\Department;
use App\Models\Hris\Employee;
use App\Models\Hris\EmployeePosition;
use App\Models\Hris\LeaveBalance;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\LeaveType;
use App\Models\Hris\Position;
use App\Models\User;
use App\Services\Hris\LeaveBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->employee = Employee::factory()->create([
        'hire_date' => now()->subYears(2),
    ]);
    $this->leaveType = LeaveType::factory()->create([
        'code' => 'ANNUAL',
        'default_annual_quota' => 12,
    ]);
    app(LeaveBalanceService::class)->ensureBalance($this->employee, $this->leaveType, now()->year);
});

it('creates leave request via api', function () {
    Sanctum::actingAs($this->employee->user);

    $response = postJson('/api/hris/leave-requests', [
        'leave_type_id' => $this->leaveType->id,
        'start_date' => now()->addDays(2)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
        'reason' => 'Family travel',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id',
                'employee_id',
                'leave_type_id',
                'start_date',
                'end_date',
                'total_days',
                'status',
            ],
        ]);

    expect(LeaveRequest::count())->toBe(1);
    $leaveRequest = LeaveRequest::first();
    expect($leaveRequest->employee_id)->toBe($this->employee->id);
    expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_PENDING);
});

it('requires authentication to create leave request', function () {
    $response = postJson('/api/hris/leave-requests', [
        'leave_type_id' => $this->leaveType->id,
        'start_date' => now()->addDays(2)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
        'reason' => 'Family travel',
    ]);

    $response->assertUnauthorized();
});

it('validates required fields when creating leave request', function () {
    Sanctum::actingAs($this->employee->user);

    $response = postJson('/api/hris/leave-requests', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['leave_type_id', 'start_date', 'end_date']);
});

it('validates end date is after start date', function () {
    Sanctum::actingAs($this->employee->user);

    $response = postJson('/api/hris/leave-requests', [
        'leave_type_id' => $this->leaveType->id,
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(2)->toDateString(),
        'reason' => 'Invalid dates',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['end_date']);
});

it('lists leave requests for authenticated employee', function () {
    Sanctum::actingAs($this->employee->user);

    LeaveRequest::factory()->count(3)->create([
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->leaveType->id,
    ]);

    $response = getJson('/api/hris/leave-requests');

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

it('cancels leave request', function () {
    Sanctum::actingAs($this->employee->user);

    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->leaveType->id,
        'status' => LeaveRequest::STATUS_PENDING,
    ]);

    $response = putJson("/api/hris/leave-requests/{$leaveRequest->id}/cancel");

    $response->assertOk();
    $leaveRequest->refresh();
    expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_CANCELLED);
});

it('can cancel approved leave request and releases balance', function () {
    Sanctum::actingAs($this->employee->user);

    // Use dates in the current year to match the balance created in beforeEach
    $startDate = now()->addDays(2);
    $endDate = now()->addDays(4);

    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $this->employee->id,
        'leave_type_id' => $this->leaveType->id,
        'status' => LeaveRequest::STATUS_APPROVED,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'total_days' => 3,
    ]);

    // Consume balance first
    app(\App\Services\Hris\LeaveBalanceService::class)->consume($leaveRequest);
    $balance = $this->employee->leaveBalances()->where('leave_type_id', $this->leaveType->id)->first();
    expect($balance->used_days)->toBe(3);

    $response = putJson("/api/hris/leave-requests/{$leaveRequest->id}/cancel");

    $response->assertOk();
    $leaveRequest->refresh();
    expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_CANCELLED);

    // Balance should be released
    $balance->refresh();
    expect($balance->used_days)->toBe(0);
});

it('returns profile data for authenticated employee', function () {
    $department = Department::factory()->create();
    $position = Position::factory()->create();

    EmployeePosition::factory()->create([
        'employee_id' => $this->employee->id,
        'department_id' => $department->id,
        'position_id' => $position->id,
        'is_primary' => true,
    ]);

    Sanctum::actingAs($this->employee->user);

    $response = getJson('/api/hris/profile');

    $response->assertOk()
        ->assertJsonStructure([
            'user' => ['id', 'name', 'email'],
            'employee' => ['id', 'nik', 'hire_date', 'current_position', 'current_department'],
        ])
        ->assertJsonPath('user.email', $this->employee->user->email);
});

it('updates employee profile', function () {
    Sanctum::actingAs($this->employee->user);

    $response = putJson('/api/hris/profile', [
        'phone' => '081234567890',
        'address' => 'New Address',
        'emergency_contact_name' => 'Emergency Contact',
        'emergency_contact_phone' => '081234567891',
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Profile updated successfully.');

    $this->employee->refresh();
    expect($this->employee->phone)->toBe('081234567890');
    expect($this->employee->address)->toBe('New Address');
});

it('returns leave balances for authenticated employee', function () {
    Sanctum::actingAs($this->employee->user);

    $sickLeaveType = LeaveType::factory()->create(['code' => 'SICK']);
    app(LeaveBalanceService::class)->ensureBalance($this->employee, $sickLeaveType, now()->year);

    $response = getJson('/api/hris/leave-balances');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'leave_type',
                    'year',
                    'starting_balance',
                    'used_days',
                    'remaining_days',
                ],
            ],
        ])
        ->assertJsonCount(2, 'data'); // Annual + Sick
});

it('returns active leave types', function () {
    Sanctum::actingAs($this->employee->user);

    LeaveType::factory()->create(['is_active' => false]);

    $response = getJson('/api/hris/leave-types');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'code', 'name', 'description', 'default_annual_quota'],
            ],
        ]);

    // Should only return active leave types
    $activeTypes = collect($response->json('data'));
    expect($activeTypes->every(fn ($type) => $type['is_active'] ?? true))->toBeTrue();
});

