<?php

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-07-20 14:00:00', 'Asia/Jakarta'));

    $this->employee = Employee::factory()->create();
    Sanctum::actingAs($this->employee->user);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('returns 422 (not 500) when starting a break without checking in first', function () {
    $response = postJson('/api/hris/attendance/break/start');

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Employee has not checked in for today.');
});

it('returns 422 (not 500) when starting a break after already checking out', function () {
    AttendanceLog::factory()->create([
        'employee_id' => $this->employee->id,
        'date' => Carbon::today('Asia/Jakarta'),
        'check_in_time' => Carbon::parse('2026-07-20 08:00:00', 'Asia/Jakarta'),
        'check_out_time' => Carbon::parse('2026-07-20 13:00:00', 'Asia/Jakarta'),
    ]);

    $response = postJson('/api/hris/attendance/break/start');

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Employee has already checked out for today.');
});

it('returns 422 (not 500) when starting a break while already on break', function () {
    AttendanceLog::factory()->checkedInOnly()->create([
        'employee_id' => $this->employee->id,
        'date' => Carbon::today('Asia/Jakarta'),
        'check_in_time' => Carbon::parse('2026-07-20 08:00:00', 'Asia/Jakarta'),
        'break_start_time' => Carbon::parse('2026-07-20 12:00:00', 'Asia/Jakarta'),
        'break_end_time' => null,
    ]);

    $response = postJson('/api/hris/attendance/break/start');

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Employee is already on break.');
});

it('returns 422 (not 500) when starting a break after already taking one today', function () {
    AttendanceLog::factory()->checkedInOnly()->create([
        'employee_id' => $this->employee->id,
        'date' => Carbon::today('Asia/Jakarta'),
        'check_in_time' => Carbon::parse('2026-07-20 08:00:00', 'Asia/Jakarta'),
        'break_start_time' => Carbon::parse('2026-07-20 12:00:00', 'Asia/Jakarta'),
        'break_end_time' => Carbon::parse('2026-07-20 12:30:00', 'Asia/Jakarta'),
    ]);

    $response = postJson('/api/hris/attendance/break/start');

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Employee has already taken a break today.');
});

it('returns 422 (not 500) when ending a break without checking in first', function () {
    $response = postJson('/api/hris/attendance/break/end');

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Employee has not checked in for today.');
});

it('returns 422 (not 500) when ending a break that was never started', function () {
    AttendanceLog::factory()->checkedInOnly()->create([
        'employee_id' => $this->employee->id,
        'date' => Carbon::today('Asia/Jakarta'),
        'check_in_time' => Carbon::parse('2026-07-20 08:00:00', 'Asia/Jakarta'),
    ]);

    $response = postJson('/api/hris/attendance/break/end');

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Employee is not currently on break.');
});
