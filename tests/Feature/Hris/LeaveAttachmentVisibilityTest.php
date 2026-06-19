<?php

use App\Models\Hris\Employee;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\LeaveType;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('private');

    Permission::firstOrCreate(['name' => 'view_leave::request', 'guard_name' => 'web']);

    $this->approver = tap(User::factory()->create())->givePermissionTo('view_leave::request');
    $this->outsider = User::factory()->create();

    $sickLeave = LeaveType::factory()->create(['requires_attachment' => true]);
    $employee = Employee::factory()->create();

    $this->leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $sickLeave->id,
        'attachment_path' => "leave-attachments/{$employee->id}/letter.pdf",
        'status' => LeaveRequest::STATUS_PENDING,
    ]);
    Storage::disk('private')->put($this->leaveRequest->attachment_path, '%PDF-1.4 doctor letter');
});

it('lets an authorized approver view the doctor letter inline', function () {
    $res = $this->actingAs($this->approver)
        ->get(route('leave-requests.attachment.view', $this->leaveRequest));

    $res->assertOk();
    expect($res->headers->get('content-disposition'))->toContain('inline');
    expect($res->headers->get('content-type'))->toContain('application/pdf');
    expect($res->headers->get('x-content-type-options'))->toBe('nosniff');
});

it('forces a smuggled HTML attachment to download as opaque bytes (no inline XSS)', function () {
    $this->leaveRequest->update(['attachment_path' => 'leave-attachments/evil/x.html']);
    Storage::disk('private')->put($this->leaveRequest->attachment_path, '<html><script>alert(1)</script></html>');

    $res = $this->actingAs($this->approver)
        ->get(route('leave-requests.attachment.view', $this->leaveRequest));

    $res->assertOk();
    expect($res->headers->get('content-disposition'))->toContain('attachment');
    expect($res->headers->get('content-type'))->toContain('application/octet-stream');
    expect($res->headers->get('x-content-type-options'))->toBe('nosniff');
});

it('forbids a user without leave view permission', function () {
    $this->actingAs($this->outsider)
        ->get(route('leave-requests.attachment.view', $this->leaveRequest))
        ->assertForbidden();
});

it('redirects a guest to login', function () {
    $this->get(route('leave-requests.attachment.view', $this->leaveRequest))
        ->assertRedirect('/login');
});

it('returns 404 when the attachment file is missing on disk', function () {
    Storage::disk('private')->delete($this->leaveRequest->attachment_path);

    $this->actingAs($this->approver)
        ->get(route('leave-requests.attachment.view', $this->leaveRequest))
        ->assertNotFound();
});

it('returns 404 when the leave request has no attachment', function () {
    $this->leaveRequest->update(['attachment_path' => null]);

    $this->actingAs($this->approver)
        ->get(route('leave-requests.attachment.view', $this->leaveRequest))
        ->assertNotFound();
});
