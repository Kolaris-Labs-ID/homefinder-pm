<?php

use App\Models\Hris\Employee;
use App\Models\Hris\LeaveBalance;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\LeaveType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeApprovedSickLeaveAwaitingCertificate(array $overrides = []): LeaveRequest
{
    $employee = Employee::factory()->create();
    $sickTypeAttributes = LeaveType::factory()->raw([
        'requires_attachment' => true,
        'default_annual_quota' => null,
    ]);
    // firstOrCreate() creates via array_merge($attributes, $values) where $values
    // wins — without this unset the factory's random code would override 'SICK'.
    unset($sickTypeAttributes['code']);
    $sickType = LeaveType::firstOrCreate(['code' => 'SICK'], $sickTypeAttributes);

    $leaveRequest = LeaveRequest::factory()->create(array_merge([
        'employee_id' => $employee->id,
        'leave_type_id' => $sickType->id,
        'status' => LeaveRequest::STATUS_PENDING,
        'attachment_path' => null,
    ], $overrides));

    $leaveRequest->markAsApproved();

    return $leaveRequest->refresh();
}

// --- hris:leave-certificate-expire ---

it('flags an approved sick leave past its certificate deadline as overdue, keeping it approved', function () {
    $leaveRequest = makeApprovedSickLeaveAwaitingCertificate();
    $leaveRequest->update(['certificate_deadline_at' => now()->subHour()]);

    $this->artisan('hris:leave-certificate-expire')->assertExitCode(0);

    $leaveRequest->refresh();
    expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_APPROVED)
        ->and($leaveRequest->certificate_status)->toBe(LeaveRequest::CERT_STATUS_OVERDUE)
        ->and($leaveRequest->rejection_reason)->toBeNull()
        ->and($leaveRequest->auto_rejected)->toBeFalse();
});

it('keeps the leave balance untouched when flagging an overdue certificate', function () {
    $leaveRequest = makeApprovedSickLeaveAwaitingCertificate();
    $leaveRequest->update(['certificate_deadline_at' => now()->subHour()]);

    $this->artisan('hris:leave-certificate-expire');

    $balance = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
        ->where('leave_type_id', $leaveRequest->leave_type_id)
        ->where('year', $leaveRequest->start_date->year)
        ->first();

    expect($balance->used_days)->toBe($leaveRequest->total_days);
});

it('flags a cert-rejected request whose deadline has passed', function () {
    $leaveRequest = makeApprovedSickLeaveAwaitingCertificate();
    $leaveRequest->update([
        'certificate_deadline_at' => now()->subHour(),
        'certificate_status' => LeaveRequest::CERT_STATUS_REJECTED,
    ]);

    $this->artisan('hris:leave-certificate-expire');

    expect($leaveRequest->refresh()->certificate_status)->toBe(LeaveRequest::CERT_STATUS_OVERDUE);
});

it('notifies HR when flagging an overdue certificate', function () {
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);
    $hrUser = tap(User::factory()->create())->assignRole('hr-admin');
    $nonHrUser = User::factory()->create();

    $leaveRequest = makeApprovedSickLeaveAwaitingCertificate();
    $leaveRequest->update(['certificate_deadline_at' => now()->subHour()]);

    $this->artisan('hris:leave-certificate-expire');

    $notifications = Notification::where('user_id', $hrUser->id)->where('type', 'certificate_overdue_hr')->get();

    expect($notifications)->toHaveCount(1)
        ->and($notifications->first()->data['leave_request_id'])->toBe($leaveRequest->id)
        ->and(Notification::where('user_id', $nonHrUser->id)->count())->toBe(0);
});

it('is idempotent: a second run does not re-flag or re-notify', function () {
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);
    $hrUser = tap(User::factory()->create())->assignRole('hr-admin');

    $leaveRequest = makeApprovedSickLeaveAwaitingCertificate();
    $leaveRequest->update(['certificate_deadline_at' => now()->subHour()]);

    $this->artisan('hris:leave-certificate-expire');
    $this->artisan('hris:leave-certificate-expire');

    expect($leaveRequest->refresh()->certificate_status)->toBe(LeaveRequest::CERT_STATUS_OVERDUE)
        ->and(Notification::where('user_id', $hrUser->id)->where('type', 'certificate_overdue_hr')->count())->toBe(1);
});

it('does not touch a leave request whose certificate was already submitted, even past the deadline', function () {
    $leaveRequest = makeApprovedSickLeaveAwaitingCertificate();
    $leaveRequest->update([
        'certificate_deadline_at' => now()->subHour(),
        'certificate_status' => LeaveRequest::CERT_STATUS_SUBMITTED,
        'certificate_submitted_at' => now()->subMinutes(30),
    ]);

    $this->artisan('hris:leave-certificate-expire');

    $leaveRequest->refresh();
    expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_APPROVED);
    expect($leaveRequest->certificate_status)->toBe(LeaveRequest::CERT_STATUS_SUBMITTED);
    expect($leaveRequest->auto_rejected)->toBeFalse();
});

it('does not touch a leave request whose certificate was already verified', function () {
    $leaveRequest = makeApprovedSickLeaveAwaitingCertificate();
    $leaveRequest->update([
        'certificate_deadline_at' => now()->subHour(),
        'certificate_status' => LeaveRequest::CERT_STATUS_VERIFIED,
        'certificate_verified_at' => now()->subMinutes(30),
    ]);

    $this->artisan('hris:leave-certificate-expire');

    $leaveRequest->refresh();
    expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_APPROVED)
        ->and($leaveRequest->certificate_status)->toBe(LeaveRequest::CERT_STATUS_VERIFIED);
});

it('does not touch a leave request whose deadline has not passed yet', function () {
    $leaveRequest = makeApprovedSickLeaveAwaitingCertificate();
    $leaveRequest->update(['certificate_deadline_at' => now()->addHour()]);

    $this->artisan('hris:leave-certificate-expire');

    $leaveRequest->refresh();
    expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_APPROVED)
        ->and($leaveRequest->certificate_status)->toBe(LeaveRequest::CERT_STATUS_PENDING);
    expect($leaveRequest->auto_rejected)->toBeFalse();
});

// --- hris:leave-certificate-remind ---

it('reminds the employee when the certificate deadline is approaching', function () {
    $leaveRequest = makeApprovedSickLeaveAwaitingCertificate();
    $leaveRequest->update(['certificate_deadline_at' => now()->addHours(12)]);

    $this->artisan('hris:leave-certificate-remind', ['--hours' => 24])->assertExitCode(0);

    $notification = Notification::where('user_id', $leaveRequest->employee->user_id)
        ->where('type', 'certificate_reminder')
        ->first();

    expect($notification)->not->toBeNull();
});

it('notifies HR users about an employee certificate reminder', function () {
    Permission::firstOrCreate(['name' => 'view_any_leave::request', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);
    $hrUser = tap(User::factory()->create())->assignRole('hr-admin');

    $leaveRequest = makeApprovedSickLeaveAwaitingCertificate();
    $leaveRequest->update(['certificate_deadline_at' => now()->addHours(12)]);

    $this->artisan('hris:leave-certificate-remind', ['--hours' => 24]);

    $notification = Notification::where('user_id', $hrUser->id)
        ->where('type', 'certificate_reminder_hr')
        ->first();

    expect($notification)->not->toBeNull();
});

it('does not remind for a deadline far outside the reminder window', function () {
    $leaveRequest = makeApprovedSickLeaveAwaitingCertificate();
    $leaveRequest->update(['certificate_deadline_at' => now()->addHours(47)]);

    $this->artisan('hris:leave-certificate-remind', ['--hours' => 24]);

    expect(Notification::where('user_id', $leaveRequest->employee->user_id)->where('type', 'certificate_reminder')->count())->toBe(0);
});

it('does not remind for a certificate that was already submitted', function () {
    $leaveRequest = makeApprovedSickLeaveAwaitingCertificate();
    $leaveRequest->update([
        'certificate_deadline_at' => now()->addHours(12),
        'certificate_status' => LeaveRequest::CERT_STATUS_SUBMITTED,
    ]);

    $this->artisan('hris:leave-certificate-remind', ['--hours' => 24]);

    expect(Notification::where('user_id', $leaveRequest->employee->user_id)->where('type', 'certificate_reminder')->count())->toBe(0);
});

it('certificateOverdue scope matches pending and cert-rejected requests past deadline', function () {
    $pending = makeApprovedSickLeaveAwaitingCertificate();
    $pending->update(['certificate_deadline_at' => now()->subHour()]);

    $certRejected = makeApprovedSickLeaveAwaitingCertificate();
    $certRejected->update([
        'certificate_deadline_at' => now()->subHour(),
        'certificate_status' => LeaveRequest::CERT_STATUS_REJECTED,
    ]);

    $future = makeApprovedSickLeaveAwaitingCertificate();

    $legacyAutoRejected = makeApprovedSickLeaveAwaitingCertificate();
    $legacyAutoRejected->update([
        'status' => LeaveRequest::STATUS_REJECTED,
        'certificate_deadline_at' => now()->subHour(),
        'certificate_status' => LeaveRequest::CERT_STATUS_EXPIRED,
        'auto_rejected' => true,
    ]);

    $ids = LeaveRequest::certificateOverdue()->pluck('id');

    expect($ids)->toContain($pending->id)
        ->toContain($certRejected->id)
        ->not->toContain($future->id)
        ->not->toContain($legacyAutoRejected->id);
});
