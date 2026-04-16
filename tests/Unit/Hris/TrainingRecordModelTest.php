<?php

use App\Models\Hris\Employee;
use App\Models\Hris\TrainingRecord;

beforeEach(function () {
    $this->employee = Employee::factory()->create();
});

test('isPaidTraining returns true for paid training', function () {
    $record = TrainingRecord::factory()
        ->forEmployee($this->employee)
        ->paidTraining()
        ->create();

    expect($record->isPaidTraining())->toBeTrue();
    expect($record->isFreeTraining())->toBeFalse();
});

test('isFreeTraining returns true for free training', function () {
    $record = TrainingRecord::factory()
        ->forEmployee($this->employee)
        ->freeTraining()
        ->create();

    expect($record->isFreeTraining())->toBeTrue();
    expect($record->isPaidTraining())->toBeFalse();
});

test('isApprovedForPgp returns true when approved', function () {
    $record = TrainingRecord::factory()
        ->forEmployee($this->employee)
        ->freeTraining()
        ->approvedForPgp()
        ->create();

    expect($record->isApprovedForPgp())->toBeTrue();
});

test('isApprovedForPgp returns false when not approved', function () {
    $record = TrainingRecord::factory()
        ->forEmployee($this->employee)
        ->freeTraining()
        ->create();

    expect($record->isApprovedForPgp())->toBeFalse();
});

test('isApprovedForPgp returns false when pgp_points is zero', function () {
    $record = TrainingRecord::factory()
        ->forEmployee($this->employee)
        ->freeTraining()
        ->create([
            'pgp_points' => 0,
            'approved_at' => now(),
        ]);

    expect($record->isApprovedForPgp())->toBeFalse();
});

test('markAsApproved does not change training status', function () {
    $record = TrainingRecord::factory()
        ->forEmployee($this->employee)
        ->inProgress()
        ->create();

    $before = $record->status;
    $record->markAsApproved();
    $record->refresh();

    expect($record->status)->toBe($before);
});

test('markAsRejected does not change training status', function () {
    $record = TrainingRecord::factory()
        ->forEmployee($this->employee)
        ->completed()
        ->create();

    $before = $record->status;
    $record->markAsRejected('Not valid');
    $record->refresh();

    expect($record->status)->toBe($before);
});

test('scopeApprovedInQuarter filters records by quarter', function () {
    TrainingRecord::factory()
        ->forEmployee($this->employee)
        ->freeTraining()
        ->approvedInQuarter(1, 2026)
        ->create();

    TrainingRecord::factory()
        ->forEmployee($this->employee)
        ->freeTraining()
        ->approvedInQuarter(2, 2026)
        ->create();

    $q1Records = TrainingRecord::approvedInQuarter(1, 2026)->get();
    $q2Records = TrainingRecord::approvedInQuarter(2, 2026)->get();

    expect($q1Records)->toHaveCount(1);
    expect($q2Records)->toHaveCount(1);
});

test('scopeWithPgpPoints filters records with points', function () {
    TrainingRecord::factory()
        ->forEmployee($this->employee)
        ->freeTraining()
        ->approvedForPgp(35)
        ->create();

    TrainingRecord::factory()
        ->forEmployee($this->employee)
        ->freeTraining()
        ->create();

    $recordsWithPoints = TrainingRecord::withPgpPoints()->get();

    expect($recordsWithPoints)->toHaveCount(1);
    expect($recordsWithPoints->first()->pgp_points)->toBe(35);
});

test('training type constants are defined correctly', function () {
    expect(TrainingRecord::TRAINING_TYPE_FREE)->toBe('free');
    expect(TrainingRecord::TRAINING_TYPE_PAID)->toBe('paid');
});

test('pgp point constants are defined correctly', function () {
    expect(TrainingRecord::PGP_FREE_TRAINING)->toBe(35);
    expect(TrainingRecord::PGP_PAID_MIN)->toBe(15);
    expect(TrainingRecord::PGP_PAID_MAX)->toBe(25);
});
