<?php

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
});

dataset('attendance photo columns', [
    'check-in photo' => 'check_in_photo_path',
    'check-out photo' => 'check_out_photo_path',
]);

it('requires an authentication token to read an attendance photo', function () {
    $this->get('/api/hris/attendance/photo?'.http_build_query([
        'path' => 'attendance-photos/2026/07/emp_1/photo.jpg',
    ]))->assertUnauthorized();
});

it('rejects an invalid authentication token', function () {
    $this->get('/api/hris/attendance/photo?'.http_build_query([
        'path' => 'attendance-photos/2026/07/emp_1/photo.jpg',
        'token' => 'invalid-token',
    ]))->assertUnauthorized();
});

it('forbids an employee from reading another employee attendance photo', function (string $photoColumn) {
    $owner = Employee::factory()->create();
    $attacker = Employee::factory()->create();
    $path = "attendance-photos/2026/07/emp_{$owner->id}/owner.jpg";

    Storage::disk('local')->put($path, 'owner-photo-bytes');
    AttendanceLog::factory()->create([
        'employee_id' => $owner->id,
        $photoColumn => $path,
    ]);

    $token = $attacker->user->createToken('attacker')->plainTextToken;

    $this->get('/api/hris/attendance/photo?'.http_build_query([
        'path' => $path,
        'token' => $token,
    ]))->assertForbidden();
})->with('attendance photo columns');

it('still serves an employee their own attendance photo', function (string $photoColumn) {
    $owner = Employee::factory()->create();
    $path = "attendance-photos/2026/07/emp_{$owner->id}/owner.jpg";

    Storage::disk('local')->put($path, 'owner-photo-bytes');
    AttendanceLog::factory()->create([
        'employee_id' => $owner->id,
        $photoColumn => $path,
    ]);

    $token = $owner->user->createToken('owner')->plainTextToken;

    $this->get('/api/hris/attendance/photo?'.http_build_query([
        'path' => $path,
        'token' => $token,
    ]))->assertOk()
        ->assertContent('owner-photo-bytes');
})->with('attendance photo columns');

it('forbids an employee from reading an unreferenced attendance photo', function () {
    $employee = Employee::factory()->create();
    $path = "attendance-photos/2026/07/emp_{$employee->id}/unreferenced.jpg";

    Storage::disk('local')->put($path, 'unreferenced-photo-bytes');
    $token = $employee->user->createToken('owner')->plainTextToken;

    $this->get('/api/hris/attendance/photo?'.http_build_query([
        'path' => $path,
        'token' => $token,
    ]))->assertForbidden();
});

it('returns 403 (not a 500 Ignition page) for a path traversal attempt', function () {
    $employee = Employee::factory()->create();
    $token = $employee->user->createToken('owner')->plainTextToken;

    $response = $this->get('/api/hris/attendance/photo?'.http_build_query([
        'path' => 'attendance-photos/../../../.env',
        'token' => $token,
    ]));

    $response->assertForbidden();
    expect($response->getContent())->not->toContain('Ignition');
});

it('returns 403 for a percent-encoded path traversal attempt', function () {
    $employee = Employee::factory()->create();
    $token = $employee->user->createToken('owner')->plainTextToken;

    $this->get('/api/hris/attendance/photo?path=attendance-photos%2F..%2F..%2F..%2F.env&token='.$token)
        ->assertForbidden();
});

it('returns 403 for a double percent-encoded path traversal attempt', function () {
    $employee = Employee::factory()->create();
    $token = $employee->user->createToken('owner')->plainTextToken;

    $this->get('/api/hris/attendance/photo?path=attendance-photos%252F..%252F..%252F..%252F.env&token='.$token)
        ->assertForbidden();
});
