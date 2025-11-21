<?php

use App\Models\Hris\Employee;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\LeaveType;
use App\Services\Hris\LeaveBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

it('creates leave request via api', function () {
    $employee = Employee::factory()->create([
        'hire_date' => now()->subYears(2),
    ]);

    $leaveType = LeaveType::factory()->create([
        'code' => 'ANNUAL',
        'default_annual_quota' => 12,
    ]);

    app(LeaveBalanceService::class)->ensureBalance($employee, $leaveType, now()->year);

    Sanctum::actingAs($employee->user);

    $response = postJson('/api/hris/leave-requests', [
        'leave_type_id' => $leaveType->id,
        'start_date' => now()->addDays(2)->toDateString(),
        'end_date' => now()->addDays(4)->toDateString(),
        'reason' => 'Family travel',
    ]);

    $response->assertCreated();
    expect(LeaveRequest::count())->toBe(1);
});

it('returns profile data for authenticated employee', function () {
    $employee = Employee::factory()->create([
        'hire_date' => now()->subYear(),
    ]);

    Sanctum::actingAs($employee->user);

    $response = getJson('/api/hris/profile');

    $response->assertOk()
        ->assertJsonPath('user.email', $employee->user->email);
});

