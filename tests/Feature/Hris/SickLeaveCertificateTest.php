<?php

use App\Models\Hris\Employee;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\LeaveType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

function makeSickLeaveRequest(array $overrides = []): LeaveRequest
{
    $employee = Employee::factory()->create();
    $sickType = LeaveType::factory()->create([
        'code' => 'SICK',
        'requires_attachment' => true,
        'default_annual_quota' => null,
    ]);

    return LeaveRequest::factory()->create(array_merge([
        'employee_id' => $employee->id,
        'leave_type_id' => $sickType->id,
        'status' => LeaveRequest::STATUS_PENDING,
        'attachment_path' => null,
    ], $overrides));
}

it('stamps a pending certificate status and a 48h deadline when a requires-attachment leave is approved without one', function () {
    $leaveRequest = makeSickLeaveRequest();

    $leaveRequest->markAsApproved();
    $leaveRequest->refresh();

    expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_APPROVED);
    expect($leaveRequest->certificate_status)->toBe(LeaveRequest::CERT_STATUS_PENDING);
    expect($leaveRequest->certificate_deadline_at)->not->toBeNull();
    expect((int) $leaveRequest->approved_at->diffInHours($leaveRequest->certificate_deadline_at))->toBe(48);
});

it('does not stamp certificate tracking when the attachment was already provided at submission', function () {
    $employee = Employee::factory()->create();
    $sickType = LeaveType::factory()->create([
        'code' => 'SICK',
        'requires_attachment' => true,
        'default_annual_quota' => null,
    ]);

    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $sickType->id,
        'status' => LeaveRequest::STATUS_PENDING,
        'attachment_path' => "leave-attachments/{$employee->id}/letter.pdf",
    ]);

    $leaveRequest->markAsApproved();
    $leaveRequest->refresh();

    expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_APPROVED);
    expect($leaveRequest->certificate_status)->toBeNull();
    expect($leaveRequest->certificate_deadline_at)->toBeNull();
});

it('does not stamp certificate tracking for leave types that do not require an attachment', function () {
    $employee = Employee::factory()->create();
    $annualType = LeaveType::factory()->create([
        'code' => 'ANNUAL',
        'requires_attachment' => false,
    ]);

    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $annualType->id,
        'status' => LeaveRequest::STATUS_PENDING,
    ]);

    $leaveRequest->markAsApproved();
    $leaveRequest->refresh();

    expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_APPROVED);
    expect($leaveRequest->certificate_status)->toBeNull();
    expect($leaveRequest->certificate_deadline_at)->toBeNull();
});

// --- Certificate upload endpoint ---

it('lets the employee upload a certificate before the deadline', function () {
    Storage::fake('private');

    $leaveRequest = makeSickLeaveRequest(['status' => LeaveRequest::STATUS_APPROVED]);
    $leaveRequest->markAsApproved();
    $leaveRequest->refresh();

    Sanctum::actingAs($leaveRequest->employee->user);

    $file = UploadedFile::fake()->create('letter.pdf', 100, 'application/pdf');

    $response = postJson("/api/hris/leave-requests/{$leaveRequest->id}/certificate", [
        'attachment' => $file,
    ]);

    $response->assertOk();
    $leaveRequest->refresh();
    expect($leaveRequest->attachment_path)->not->toBeNull();
    expect($leaveRequest->certificate_status)->toBe(LeaveRequest::CERT_STATUS_SUBMITTED);
    expect($leaveRequest->certificate_submitted_at)->not->toBeNull();
    Storage::disk('private')->assertExists($leaveRequest->attachment_path);
});

it('forbids uploading a certificate to another employees leave request', function () {
    Storage::fake('private');

    $leaveRequest = makeSickLeaveRequest(['status' => LeaveRequest::STATUS_APPROVED]);
    $leaveRequest->markAsApproved();

    $otherEmployee = Employee::factory()->create();
    Sanctum::actingAs($otherEmployee->user);

    $response = postJson("/api/hris/leave-requests/{$leaveRequest->id}/certificate", [
        'attachment' => UploadedFile::fake()->create('letter.pdf', 100, 'application/pdf'),
    ]);

    $response->assertForbidden();
});

it('rejects a certificate upload once the submission window has expired', function () {
    Storage::fake('private');

    $leaveRequest = makeSickLeaveRequest(['status' => LeaveRequest::STATUS_APPROVED]);
    $leaveRequest->markAsApproved();
    $leaveRequest->update(['certificate_status' => LeaveRequest::CERT_STATUS_EXPIRED, 'auto_rejected' => true]);

    Sanctum::actingAs($leaveRequest->employee->user);

    $response = postJson("/api/hris/leave-requests/{$leaveRequest->id}/certificate", [
        'attachment' => UploadedFile::fake()->create('letter.pdf', 100, 'application/pdf'),
    ]);

    $response->assertUnprocessable();
});

it('rejects a certificate upload once the deadline has passed even if hris:leave-certificate-expire has not run yet', function () {
    Storage::fake('private');

    $leaveRequest = makeSickLeaveRequest(['status' => LeaveRequest::STATUS_APPROVED]);
    $leaveRequest->markAsApproved();
    // Simulate the deadline passing without the hourly scheduler having caught up:
    // certificate_status is still 'pending', only the timestamp has moved.
    $leaveRequest->update(['certificate_deadline_at' => now()->subHour()]);

    Sanctum::actingAs($leaveRequest->employee->user);

    $response = postJson("/api/hris/leave-requests/{$leaveRequest->id}/certificate", [
        'attachment' => UploadedFile::fake()->create('letter.pdf', 100, 'application/pdf'),
    ]);

    $response->assertUnprocessable();
    $leaveRequest->refresh();
    expect($leaveRequest->certificate_status)->toBe(LeaveRequest::CERT_STATUS_PENDING);
    Storage::disk('private')->assertMissing(($leaveRequest->attachment_path ?? 'none'));
});

it('rejects a certificate upload for a leave request that never required one', function () {
    $employee = Employee::factory()->create();
    $annualType = LeaveType::factory()->create(['code' => 'ANNUAL', 'requires_attachment' => false]);
    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $annualType->id,
        'status' => LeaveRequest::STATUS_APPROVED,
    ]);

    Sanctum::actingAs($employee->user);

    $response = postJson("/api/hris/leave-requests/{$leaveRequest->id}/certificate", [
        'attachment' => UploadedFile::fake()->create('letter.pdf', 100, 'application/pdf'),
    ]);

    $response->assertUnprocessable();
});

it('lets the employee resubmit a certificate after HR rejected the first one', function () {
    Storage::fake('private');

    $leaveRequest = makeSickLeaveRequest(['status' => LeaveRequest::STATUS_APPROVED]);
    $leaveRequest->markAsApproved();
    $leaveRequest->update([
        'certificate_status' => LeaveRequest::CERT_STATUS_REJECTED,
        'certificate_rejection_reason' => 'Illegible scan',
    ]);

    Sanctum::actingAs($leaveRequest->employee->user);

    $response = postJson("/api/hris/leave-requests/{$leaveRequest->id}/certificate", [
        'attachment' => UploadedFile::fake()->create('letter2.pdf', 100, 'application/pdf'),
    ]);

    $response->assertOk();
    $leaveRequest->refresh();
    expect($leaveRequest->certificate_status)->toBe(LeaveRequest::CERT_STATUS_SUBMITTED);
});

it('notifies HR when the employee submits a certificate', function () {
    Storage::fake('private');
    Role::firstOrCreate(['name' => 'hr-admin', 'guard_name' => 'web']);
    $hrUser = tap(User::factory()->create())->assignRole('hr-admin');

    $leaveRequest = makeSickLeaveRequest(['status' => LeaveRequest::STATUS_APPROVED]);
    $leaveRequest->markAsApproved();

    Sanctum::actingAs($leaveRequest->employee->user);

    postJson("/api/hris/leave-requests/{$leaveRequest->id}/certificate", [
        'attachment' => UploadedFile::fake()->create('letter.pdf', 100, 'application/pdf'),
    ])->assertOk();

    $notification = Notification::where('user_id', $hrUser->id)
        ->where('type', 'certificate_submitted_hr')
        ->first();

    expect($notification)->not->toBeNull();
});

it('exposes certificate tracking fields in the leave-requests list API', function () {
    $leaveRequest = makeSickLeaveRequest(['status' => LeaveRequest::STATUS_APPROVED]);
    $leaveRequest->markAsApproved();

    Sanctum::actingAs($leaveRequest->employee->user);

    $response = \Pest\Laravel\getJson('/api/hris/leave-requests');

    $response->assertOk()->assertJsonPath('data.0.certificate_status', LeaveRequest::CERT_STATUS_PENDING);
    expect($response->json('data.0'))->toHaveKeys([
        'certificate_status',
        'certificate_deadline_at',
        'certificate_submitted_at',
        'certificate_verified_at',
        'certificate_rejection_reason',
    ]);
});

// --- HR verify/reject certificate action ---

it('lets HR verify a submitted certificate, leaving the leave approved and untouched otherwise', function () {
    $leaveRequest = makeSickLeaveRequest(['status' => LeaveRequest::STATUS_APPROVED]);
    $leaveRequest->markAsApproved();
    $leaveRequest->update(['certificate_status' => LeaveRequest::CERT_STATUS_SUBMITTED, 'certificate_submitted_at' => now()]);

    $hrUser = \App\Models\User::factory()->create();

    \App\Filament\Resources\LeaveRequestResource\Pages\LeaveRequestActionHandler::verifyCertificate($leaveRequest, $hrUser);

    $leaveRequest->refresh();
    expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_APPROVED);
    expect($leaveRequest->certificate_status)->toBe(LeaveRequest::CERT_STATUS_VERIFIED);
    expect($leaveRequest->certificate_verified_at)->not->toBeNull();
    expect($leaveRequest->certificate_verified_by)->toBe($hrUser->id);
});

it('lets HR reject a submitted certificate without rejecting the leave itself', function () {
    $leaveRequest = makeSickLeaveRequest(['status' => LeaveRequest::STATUS_APPROVED]);
    $leaveRequest->markAsApproved();
    $leaveRequest->update(['certificate_status' => LeaveRequest::CERT_STATUS_SUBMITTED, 'certificate_submitted_at' => now()]);

    $hrUser = \App\Models\User::factory()->create();

    \App\Filament\Resources\LeaveRequestResource\Pages\LeaveRequestActionHandler::rejectCertificate($leaveRequest, $hrUser, 'Illegible scan');

    $leaveRequest->refresh();
    expect($leaveRequest->status)->toBe(LeaveRequest::STATUS_APPROVED);
    expect($leaveRequest->certificate_status)->toBe(LeaveRequest::CERT_STATUS_REJECTED);
    expect($leaveRequest->certificate_rejection_reason)->toBe('Illegible scan');
});

it('refuses to verify a certificate that has not been submitted', function () {
    $leaveRequest = makeSickLeaveRequest(['status' => LeaveRequest::STATUS_APPROVED]);
    $leaveRequest->markAsApproved();

    $hrUser = \App\Models\User::factory()->create();

    expect(fn () => \App\Filament\Resources\LeaveRequestResource\Pages\LeaveRequestActionHandler::verifyCertificate($leaveRequest, $hrUser))
        ->toThrow(\Exception::class);
});
