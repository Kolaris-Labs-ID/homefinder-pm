<?php

use App\Models\Hris\Employee;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\LeaveType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

function makeApprovedLeaveFor(Employee $employee, string $code, array $overrides = []): LeaveRequest
{
    $type = LeaveType::factory()->create(['code' => $code, 'requires_attachment' => $code === 'SICK']);

    return LeaveRequest::factory()->create(array_merge([
        'employee_id' => $employee->id,
        'leave_type_id' => $type->id,
        'status' => LeaveRequest::STATUS_APPROVED,
        'start_date' => today(),
        'end_date' => today(),
    ], $overrides));
}

it('labels a teammate on approved Sick Leave as "On Sick Leave" with certificate_status exposed', function () {
    $viewer = Employee::factory()->create();
    $sickEmployee = Employee::factory()->create();
    makeApprovedLeaveFor($sickEmployee, 'SICK', ['certificate_status' => LeaveRequest::CERT_STATUS_PENDING]);

    Sanctum::actingAs($viewer->user);

    $response = getJson('/api/hris/team');

    $response->assertOk();
    $member = collect($response->json('data'))->firstWhere('id', $sickEmployee->id);

    expect($member)->not->toBeNull();
    expect($member['status'])->toBe('on_leave');
    expect($member['status_label'])->toBe('On Sick Leave');
    expect($member['certificate_status'])->toBe('pending');
});

it('exposes certificate_status as verified once HR has verified the certificate', function () {
    $viewer = Employee::factory()->create();
    $sickEmployee = Employee::factory()->create();
    makeApprovedLeaveFor($sickEmployee, 'SICK', ['certificate_status' => LeaveRequest::CERT_STATUS_VERIFIED]);

    Sanctum::actingAs($viewer->user);

    $response = getJson('/api/hris/team');
    $member = collect($response->json('data'))->firstWhere('id', $sickEmployee->id);

    expect($member['status_label'])->toBe('On Sick Leave');
    expect($member['certificate_status'])->toBe('verified');
});

it('keeps the generic "On Leave" label and a null certificate_status for non-Sick leave types', function () {
    $viewer = Employee::factory()->create();
    $annualEmployee = Employee::factory()->create();
    makeApprovedLeaveFor($annualEmployee, 'ANNUAL');

    Sanctum::actingAs($viewer->user);

    $response = getJson('/api/hris/team');
    $member = collect($response->json('data'))->firstWhere('id', $annualEmployee->id);

    expect($member['status_label'])->toBe('On Leave');
    expect($member['certificate_status'])->toBeNull();
});
