<?php

use App\Models\Hris\Employee;
use App\Models\Hris\OvertimeLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function createRunningLog(int $hoursAgo = 7): OvertimeLog
{
    $user    = User::factory()->create();
    $manager = User::factory()->create();

    Employee::factory()->create(['user_id' => $user->id, 'is_active' => true]);

    return OvertimeLog::create([
        'user_id'    => $user->id,
        'manager_id' => $manager->id,
        'task_name'  => 'Long running task',
        'start_time' => now()->subHours($hoursAgo),
        'hourly_rate'=> 50_000,
        'status'     => OvertimeLog::STATUS_RUNNING,
    ]);
}

test('auto-close command closes sessions older than threshold', function () {
    $log = createRunningLog(7); // 7 hours old, threshold is 6

    Artisan::call('lembur:auto-close');

    $log->refresh();

    expect($log->status)->toBe(OvertimeLog::STATUS_FLAGGED)
        ->and($log->auto_closed)->toBeTrue()
        ->and($log->end_time)->not->toBeNull()
        ->and($log->duration_minutes)->toBe(4 * 60); // capped at 4 hours
});

test('auto-close command does not touch sessions within threshold', function () {
    $log = createRunningLog(3); // only 3 hours old, threshold is 6

    Artisan::call('lembur:auto-close');

    $log->refresh();

    expect($log->status)->toBe(OvertimeLog::STATUS_RUNNING);
});

test('auto-close dry-run does not persist changes', function () {
    $log = createRunningLog(8);

    Artisan::call('lembur:auto-close', ['--dry-run' => true]);

    $log->refresh();

    expect($log->status)->toBe(OvertimeLog::STATUS_RUNNING)
        ->and($log->auto_closed)->toBeFalse();
});

test('auto-close custom threshold and cap', function () {
    $log = createRunningLog(10); // 10 hours old

    Artisan::call('lembur:auto-close', ['--hours' => 8, '--cap' => 6]);

    $log->refresh();

    expect($log->status)->toBe(OvertimeLog::STATUS_FLAGGED)
        ->and($log->duration_minutes)->toBe(6 * 60);
});
