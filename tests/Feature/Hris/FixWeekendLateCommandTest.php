<?php

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\Employee;
use App\Models\Hris\LateNotice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

const SAT = '2026-04-11'; // Saturday
const MON = '2026-04-06'; // Monday

beforeEach(function () {
    $user = User::factory()->create();
    $this->employee = Employee::factory()->create(['user_id' => $user->id, 'is_active' => true]);

    $this->lateLog = function (string $date): AttendanceLog {
        return AttendanceLog::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => $date,
            'status' => AttendanceLog::STATUS_LATE,
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
            'check_in_time' => $date.' 09:30:00',
        ]);
    };

    $this->notice = function (string $date, int $logId, string $status): LateNotice {
        return LateNotice::create([
            'employee_id' => $this->employee->id,
            'attendance_log_id' => $logId,
            'date' => $date,
            'minutes_late' => 30,
            'penalty_percent' => 25,
            'status' => $status,
        ]);
    };
});

it('re-statuses weekend late logs to present and leaves weekday late logs alone', function () {
    $sat = ($this->lateLog)(SAT);
    $mon = ($this->lateLog)(MON);

    artisan('attendance:fix-weekend-late')->assertExitCode(0);

    expect($sat->fresh()->status)->toBe(AttendanceLog::STATUS_PRESENT)
        ->and($mon->fresh()->status)->toBe(AttendanceLog::STATUS_LATE);
});

it('deletes pending weekend late notices and preserves weekday notices', function () {
    $sat = ($this->lateLog)(SAT);
    $mon = ($this->lateLog)(MON);
    $satNotice = ($this->notice)(SAT, $sat->id, LateNotice::STATUS_PENDING);
    $monNotice = ($this->notice)(MON, $mon->id, LateNotice::STATUS_PENDING);

    artisan('attendance:fix-weekend-late')->assertExitCode(0);

    expect(LateNotice::find($satNotice->id))->toBeNull()
        ->and(LateNotice::find($monNotice->id))->not->toBeNull();
});

it('preserves approved or rejected weekend notices for manual HR review', function () {
    $sat = ($this->lateLog)(SAT);
    $approved = ($this->notice)(SAT, $sat->id, LateNotice::STATUS_APPROVED);

    artisan('attendance:fix-weekend-late')->assertExitCode(0);

    expect(LateNotice::find($approved->id))->not->toBeNull();
});

it('makes no changes in dry-run mode', function () {
    $sat = ($this->lateLog)(SAT);
    $satNotice = ($this->notice)(SAT, $sat->id, LateNotice::STATUS_PENDING);

    artisan('attendance:fix-weekend-late', ['--dry-run' => true])->assertExitCode(0);

    expect($sat->fresh()->status)->toBe(AttendanceLog::STATUS_LATE)
        ->and(LateNotice::find($satNotice->id))->not->toBeNull();
});

it('does not touch non-late weekend logs', function () {
    $satPresent = AttendanceLog::factory()->create([
        'employee_id' => $this->employee->id,
        'date' => SAT,
        'status' => AttendanceLog::STATUS_HALF_DAY,
        'mode' => AttendanceLog::MODE_OFFICE,
        'source' => AttendanceLog::SOURCE_WEB,
        'check_in_time' => SAT.' 08:00:00',
    ]);

    artisan('attendance:fix-weekend-late')->assertExitCode(0);

    expect($satPresent->fresh()->status)->toBe(AttendanceLog::STATUS_HALF_DAY);
});
