<?php

use App\Models\Hris\OvertimeLog;

// ============================================================================
// Constants
// ============================================================================

test('status constants have correct values', function () {
    expect(OvertimeLog::STATUS_RUNNING)->toBe('running')
        ->and(OvertimeLog::STATUS_PENDING)->toBe('pending')
        ->and(OvertimeLog::STATUS_APPROVED)->toBe('approved')
        ->and(OvertimeLog::STATUS_REJECTED)->toBe('rejected')
        ->and(OvertimeLog::STATUS_FLAGGED)->toBe('flagged');
});

test('getStatusLabels returns all statuses', function () {
    $labels = OvertimeLog::getStatusLabels();

    expect($labels)->toHaveKey('running')
        ->toHaveKey('pending')
        ->toHaveKey('approved')
        ->toHaveKey('rejected')
        ->toHaveKey('flagged');
});

// ============================================================================
// Pay calculation
// ============================================================================

test('calculateEstimatedPay returns 0 when hourly_rate is 0', function () {
    $log = new OvertimeLog([
        'hourly_rate'      => 0,
        'duration_minutes' => 120,
    ]);

    expect($log->calculateEstimatedPay())->toBe(0.0);
});

test('calculateEstimatedPay returns 0 when duration is 0', function () {
    $log = new OvertimeLog([
        'hourly_rate'      => 100_000,
        'duration_minutes' => 0,
    ]);

    expect($log->calculateEstimatedPay())->toBe(0.0);
});

test('calculateEstimatedPay for exactly 1 hour applies 1.5x', function () {
    $rate = 100_000.0;
    $log  = new OvertimeLog([
        'hourly_rate'      => $rate,
        'duration_minutes' => 60,
    ]);

    // 1 hour × 1.5 × rate
    expect($log->calculateEstimatedPay())->toBe($rate * 1.5);
});

test('calculateEstimatedPay for 2 hours uses 1.5x first hour + 2x second hour', function () {
    $rate = 100_000.0;
    $log  = new OvertimeLog([
        'hourly_rate'      => $rate,
        'duration_minutes' => 120,
    ]);

    $expected = ($rate * 1.5) + ($rate * 2.0 * 1.0);

    expect($log->calculateEstimatedPay())->toBe($expected);
});

test('calculateEstimatedPay for 30 minutes is half of 1-hour rate at 1.5x', function () {
    $rate = 100_000.0;
    $log  = new OvertimeLog([
        'hourly_rate'      => $rate,
        'duration_minutes' => 30,
    ]);

    $expected = $rate * 1.5 * 0.5;

    expect(round($log->calculateEstimatedPay(), 2))->toBe(round($expected, 2));
});

test('calculateEstimatedPay accepts override minutes parameter', function () {
    $rate = 50_000.0;
    $log  = new OvertimeLog([
        'hourly_rate'      => $rate,
        'duration_minutes' => 0,
    ]);

    // Supply 60 minutes via override
    $expected = $rate * 1.5;

    expect($log->calculateEstimatedPay(60))->toBe($expected);
});

// ============================================================================
// hasClient
// ============================================================================

test('hasClient returns false when client_name is null', function () {
    $log = new OvertimeLog(['client_name' => null]);
    expect($log->hasClient())->toBeFalse();
});

test('hasClient returns false when client_name is empty string', function () {
    $log = new OvertimeLog(['client_name' => '']);
    expect($log->hasClient())->toBeFalse();
});

test('hasClient returns true when client_name is set', function () {
    $log = new OvertimeLog(['client_name' => 'Acme Corp']);
    expect($log->hasClient())->toBeTrue();
});

// ============================================================================
// Fillable / casts
// ============================================================================

test('required fields are in fillable', function () {
    $fillable = (new OvertimeLog)->getFillable();

    foreach (['user_id', 'manager_id', 'task_name', 'start_time', 'hourly_rate', 'status'] as $field) {
        expect($fillable)->toContain($field);
    }
});
