<?php

use App\Models\Hris\Employee;
use App\Models\Hris\LeaveBalance;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\LeaveType;
use App\Models\Notification;
use App\Models\User;
use App\Services\Hris\SickLeaveResolutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

/**
 * Approved sick leave already flagged overdue, with an ANNUAL type + balance
 * row holding exactly $annualRemaining days for the leave's start year.
 */
function makeOverdueSickLeave(int $totalDays = 3, int $annualRemaining = 12, bool $provisionAnnualBalance = true): LeaveRequest
{
    $employee = Employee::factory()->create();

    $sickAttributes = LeaveType::factory()->raw([
        'requires_attachment' => true,
        'default_annual_quota' => null,
    ]);
    unset($sickAttributes['code']);
    $sickType = LeaveType::firstOrCreate(['code' => 'SICK'], $sickAttributes);

    $annualAttributes = LeaveType::factory()->raw();
    unset($annualAttributes['code']);
    $annualType = LeaveType::firstOrCreate(['code' => 'ANNUAL'], $annualAttributes);

    $start = now()->addWeek()->startOfDay();

    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $sickType->id,
        'start_date' => $start,
        'end_date' => $start->copy()->addDays($totalDays - 1),
        'total_days' => $totalDays,
        'status' => LeaveRequest::STATUS_PENDING,
        'attachment_path' => null,
    ]);

    $leaveRequest->markAsApproved();

    if ($provisionAnnualBalance) {
        LeaveBalance::updateOrCreate(
            ['employee_id' => $employee->id, 'leave_type_id' => $annualType->id, 'year' => $start->year],
            ['starting_balance' => 12, 'used_days' => 12 - $annualRemaining, 'remaining_days' => $annualRemaining]
        );
    }

    $leaveRequest->update([
        'certificate_deadline_at' => now()->subHour(),
        'certificate_status' => LeaveRequest::CERT_STATUS_OVERDUE,
    ]);

    return $leaveRequest->refresh();
}

function resolveOverdueSickLeave(LeaveRequest $leaveRequest, ?User $resolver = null): array
{
    return app(SickLeaveResolutionService::class)->resolve($leaveRequest, $resolver ?? User::factory()->create());
}

it('converts all days to annual leave when quota covers the whole request', function () {
    $sick = makeOverdueSickLeave(totalDays: 3, annualRemaining: 12);
    $hr = User::factory()->create();

    $result = resolveOverdueSickLeave($sick, $hr);

    expect($result['resolution'])->toBe(LeaveRequest::RESOLUTION_CONVERTED)
        ->and($result['converted_days'])->toBe(3)
        ->and($result['alpha_days'])->toBe(0);

    $sick->refresh();
    expect($sick->status)->toBe(LeaveRequest::STATUS_REJECTED)
        ->and($sick->certificate_status)->toBe(LeaveRequest::CERT_STATUS_RESOLVED)
        ->and($sick->certificate_resolution)->toBe(LeaveRequest::RESOLUTION_CONVERTED)
        ->and($sick->certificate_resolved_by)->toBe($hr->id)
        ->and($sick->certificate_resolved_at)->not->toBeNull();

    $annual = LeaveRequest::findOrFail($result['annual_leave_request_id']);
    expect($annual->status)->toBe(LeaveRequest::STATUS_APPROVED)
        ->and($annual->leaveType->code)->toBe('ANNUAL')
        ->and($annual->total_days)->toBe(3)
        ->and($annual->start_date->toDateString())->toBe($sick->start_date->toDateString())
        ->and($annual->end_date->toDateString())->toBe($sick->start_date->copy()->addDays(2)->toDateString())
        ->and($annual->converted_from_leave_request_id)->toBe($sick->id);
});

it('splits into annual and alpha when quota only partially covers', function () {
    $sick = makeOverdueSickLeave(totalDays: 3, annualRemaining: 1);

    $result = resolveOverdueSickLeave($sick);

    expect($result['resolution'])->toBe(LeaveRequest::RESOLUTION_PARTIAL_ALPHA)
        ->and($result['converted_days'])->toBe(1)
        ->and($result['alpha_days'])->toBe(2);

    $annual = LeaveRequest::findOrFail($result['annual_leave_request_id']);
    expect($annual->total_days)->toBe(1)
        ->and($annual->end_date->toDateString())->toBe($annual->start_date->toDateString());

    expect($sick->refresh()->certificate_resolution)->toBe(LeaveRequest::RESOLUTION_PARTIAL_ALPHA);
});

it('resolves to full alpha when no annual quota remains', function () {
    $sick = makeOverdueSickLeave(totalDays: 3, annualRemaining: 0);

    $result = resolveOverdueSickLeave($sick);

    expect($result['resolution'])->toBe(LeaveRequest::RESOLUTION_ALPHA)
        ->and($result['converted_days'])->toBe(0)
        ->and($result['alpha_days'])->toBe(3)
        ->and($result['annual_leave_request_id'])->toBeNull();

    expect(LeaveRequest::where('converted_from_leave_request_id', $sick->id)->exists())->toBeFalse();
});

it('releases the sick usage counter and consumes annual quota', function () {
    $sick = makeOverdueSickLeave(totalDays: 3, annualRemaining: 12);

    $sickBalance = LeaveBalance::where('employee_id', $sick->employee_id)
        ->where('leave_type_id', $sick->leave_type_id)
        ->where('year', $sick->start_date->year)
        ->first();
    expect($sickBalance->used_days)->toBe(3);

    resolveOverdueSickLeave($sick);

    $annualType = LeaveType::where('code', 'ANNUAL')->first();
    $annualBalance = LeaveBalance::where('employee_id', $sick->employee_id)
        ->where('leave_type_id', $annualType->id)
        ->where('year', $sick->start_date->year)
        ->first();

    expect($sickBalance->refresh()->used_days)->toBe(0)
        ->and($annualBalance->used_days)->toBe(3)
        ->and($annualBalance->remaining_days)->toBe(9);
});

it('converts using the default quota when the employee has no annual balance row yet', function () {
    $sick = makeOverdueSickLeave(totalDays: 3, provisionAnnualBalance: false);

    $result = resolveOverdueSickLeave($sick);

    expect($result['resolution'])->toBe(LeaveRequest::RESOLUTION_CONVERTED)
        ->and($result['converted_days'])->toBe(3)
        ->and($result['alpha_days'])->toBe(0);

    $annualType = LeaveType::where('code', 'ANNUAL')->first();
    $balance = LeaveBalance::where('employee_id', $sick->employee_id)
        ->where('leave_type_id', $annualType->id)
        ->where('year', $sick->start_date->year)
        ->first();

    expect($balance)->not->toBeNull()
        ->and($balance->used_days)->toBe(3)
        ->and($balance->remaining_days)->toBe(9);
});

it('refuses to resolve a request that is not overdue', function () {
    $sick = makeOverdueSickLeave();
    $sick->update(['certificate_status' => LeaveRequest::CERT_STATUS_PENDING]);

    resolveOverdueSickLeave($sick);
})->throws(Exception::class, 'not awaiting certificate resolution');

it('refuses double resolution', function () {
    $sick = makeOverdueSickLeave(totalDays: 3, annualRemaining: 12);

    resolveOverdueSickLeave($sick);

    expect(fn () => resolveOverdueSickLeave($sick->refresh()))
        ->toThrow(Exception::class, 'not awaiting certificate resolution');

    expect(LeaveRequest::where('converted_from_leave_request_id', $sick->id)->count())->toBe(1);
});

it('notifies the employee with the outcome breakdown', function () {
    $sick = makeOverdueSickLeave(totalDays: 3, annualRemaining: 1);

    resolveOverdueSickLeave($sick);

    $notification = Notification::where('user_id', $sick->employee->user_id)
        ->where('type', 'sick_leave_resolved')
        ->first();

    expect($notification)->not->toBeNull()
        ->and($notification->data['converted_days'])->toBe(1)
        ->and($notification->data['alpha_days'])->toBe(2);
});

it('resolves via the Filament action handler', function () {
    $sick = makeOverdueSickLeave(totalDays: 2, annualRemaining: 12);
    $hr = User::factory()->create();

    $result = \App\Filament\Resources\LeaveRequestResource\Pages\LeaveRequestActionHandler::resolveCertificate($sick, $hr);

    expect($result['resolution'])->toBe(LeaveRequest::RESOLUTION_CONVERTED)
        ->and($sick->refresh()->certificate_status)->toBe(LeaveRequest::CERT_STATUS_RESOLVED);
});

it('previews the split and warns about locked payroll batches', function () {
    $sick = makeOverdueSickLeave(totalDays: 3, annualRemaining: 1);

    $preview = \App\Filament\Resources\LeaveRequestResource::certificateResolutionPreview($sick);

    expect($preview)->toContain('1 day(s) will convert to Annual Leave')
        ->toContain('2 day(s) will become Alpha')
        ->and($preview)->not->toContain('Warning');

    \App\Models\Hris\PayrunBatch::factory()->create([
        'period_start_date' => $sick->start_date->copy()->startOfMonth(),
        'period_end_date' => $sick->start_date->copy()->endOfMonth(),
        'status' => \App\Models\Hris\PayrunBatch::STATUS_PAID,
    ]);

    expect(\App\Filament\Resources\LeaveRequestResource::certificateResolutionPreview($sick->refresh()))
        ->toContain('Warning');
});

it('previews the real quota when the employee has no annual balance row yet', function () {
    $sick = makeOverdueSickLeave(totalDays: 3, provisionAnnualBalance: false);

    $preview = \App\Filament\Resources\LeaveRequestResource::certificateResolutionPreview($sick);

    expect($preview)->toContain('12 annual day(s) remaining')
        ->toContain('3 day(s) will convert to Annual Leave');
});

it('exposes resolution fields in the leave requests API payload', function () {
    $sick = makeOverdueSickLeave(totalDays: 3, annualRemaining: 1);
    resolveOverdueSickLeave($sick);

    Sanctum::actingAs($sick->employee->user);

    $response = getJson('/api/hris/leave-requests')->assertOk();

    $row = collect($response->json('data'))->firstWhere('id', $sick->id);

    expect($row['certificate_status'])->toBe(LeaveRequest::CERT_STATUS_RESOLVED)
        ->and($row['certificate_resolution'])->toBe(LeaveRequest::RESOLUTION_PARTIAL_ALPHA)
        ->and($row)->toHaveKey('converted_from_leave_request_id');
});
