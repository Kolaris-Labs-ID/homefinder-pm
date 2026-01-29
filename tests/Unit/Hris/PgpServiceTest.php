<?php

use App\Services\Hris\PgpService;
use Carbon\Carbon;

beforeEach(function () {
    $this->service = new PgpService();
});

/**
 * Property 1: Quarter Determination
 * For any date within a calendar year, the getQuarterForDate() function SHALL correctly
 * identify the quarter as Q1 (Jan-Mar), Q2 (Apr-Jun), Q3 (Jul-Sep), or Q4 (Oct-Dec),
 * and return accurate start/end boundaries.
 *
 * Validates: Requirements 1.1
 */
test('Property 1: Quarter Determination - correctly identifies quarter for any date', function () {
    // Generate random date within a reasonable range
    $year = fake()->numberBetween(2020, 2030);
    $month = fake()->numberBetween(1, 12);
    $day = fake()->numberBetween(1, 28); // Use 28 to avoid month boundary issues

    $date = Carbon::create($year, $month, $day);
    $result = $this->service->getQuarterForDate($date);

    // Determine expected quarter based on month
    $expectedQuarter = (int) ceil($month / 3);

    // Verify quarter is correct
    expect($result['quarter'])->toBe($expectedQuarter);
    expect($result['year'])->toBe($year);

    // Verify start date is first day of quarter
    $expectedStartMonth = ($expectedQuarter - 1) * 3 + 1;
    expect($result['start']->month)->toBe($expectedStartMonth);
    expect($result['start']->day)->toBe(1);
    expect($result['start']->year)->toBe($year);

    // Verify end date is last moment of quarter
    $expectedEndMonth = $expectedQuarter * 3;
    expect($result['end']->month)->toBe($expectedEndMonth);
    expect($result['end']->year)->toBe($year);

    // Verify the date falls within the quarter boundaries
    expect($date->between($result['start'], $result['end']))->toBeTrue();
})->repeat(100);


/**
 * Property 2: Quarterly Point Filtering
 * For any collection of TrainingRecords with various approved_at timestamps,
 * the getPointsForQuarter() function SHALL only sum PGP points from records
 * where approved_at falls within the specified quarter boundaries.
 *
 * Validates: Requirements 1.2, 1.3
 */
test('Property 2: Quarterly Point Filtering - only sums points from specified quarter', function () {
    $employee = \App\Models\Hris\Employee::factory()->create();

    // Generate random target quarter
    $targetQuarter = fake()->numberBetween(1, 4);
    $targetYear = fake()->numberBetween(2024, 2026);

    // Create training records in the target quarter
    $targetQuarterCount = fake()->numberBetween(1, 3);
    $targetQuarterPoints = 0;

    for ($i = 0; $i < $targetQuarterCount; $i++) {
        $points = fake()->randomElement([35, fake()->numberBetween(15, 25)]);
        $targetQuarterPoints += $points;

        \App\Models\Hris\TrainingRecord::factory()
            ->forEmployee($employee)
            ->approvedInQuarter($targetQuarter, $targetYear, $points)
            ->create();
    }

    // Create training records in different quarters (should not be counted)
    $otherQuarter = $targetQuarter === 4 ? 1 : $targetQuarter + 1;
    $otherYear = $targetQuarter === 4 ? $targetYear + 1 : $targetYear;

    \App\Models\Hris\TrainingRecord::factory()
        ->forEmployee($employee)
        ->approvedInQuarter($otherQuarter, $otherYear, 35)
        ->create();

    // Get points for target quarter
    $result = $this->service->getPointsForQuarter($employee, $targetQuarter, $targetYear);

    // Verify only target quarter points are summed
    expect($result)->toBe($targetQuarterPoints);
})->repeat(100);


/**
 * Property 3: Free Training Fixed Points
 * For any TrainingRecord with training_type = 'free', the getPointsForTrainingType()
 * function SHALL always return exactly 35 PGP points.
 *
 * Validates: Requirements 2.1
 */
test('Property 3: Free Training Fixed Points - always returns 35 for free training', function () {
    // Generate random custom points (should be ignored for free training)
    $randomCustomPoints = fake()->numberBetween(1, 100);

    $result = $this->service->getPointsForTrainingType('free', $randomCustomPoints);

    expect($result)->toBe(35);
})->repeat(100);

/**
 * Property 4: Paid Training Validation
 * For any integer value, the validatePaidTrainingPoints() function SHALL return true
 * if and only if the value is between 15 and 25 inclusive.
 *
 * Validates: Requirements 2.3, 2.4
 */
test('Property 4: Paid Training Validation - validates points in range 15-25', function () {
    // Generate random points value
    $points = fake()->numberBetween(-100, 200);

    $result = $this->service->validatePaidTrainingPoints($points);

    $expectedValid = $points >= 15 && $points <= 25;

    expect($result)->toBe($expectedValid);
})->repeat(100);


/**
 * Property 5: Reward Calculation with Floor Logic
 * For any non-negative integer representing total PGP points:
 * - If points < 150, reward SHALL be 0
 * - If points >= 150, reward SHALL be 150000 + floor((points - 150) / 35) * 35000
 *
 * Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5
 */
test('Property 5: Reward Calculation with Floor Logic - calculates reward correctly', function () {
    // Generate random points value (0 to 500)
    $points = fake()->numberBetween(0, 500);

    $result = $this->service->calculateReward($points);

    if ($points < 150) {
        expect($result)->toBe(0);
    } else {
        $expected = 150000 + (int) floor(($points - 150) / 35) * 35000;
        expect($result)->toBe($expected);
    }
})->repeat(100);


/**
 * Property 6: Progress Percentage Calculation
 * For any non-negative integer representing current PGP points, the getProgressPercentage()
 * function SHALL return min(100, (points / 150) * 100), capped at 100%.
 *
 * Validates: Requirements 5.2
 */
test('Property 6: Progress Percentage Calculation - calculates progress correctly', function () {
    // Generate random points value (0 to 300)
    $points = fake()->numberBetween(0, 300);

    $result = $this->service->getProgressPercentage($points);

    $expected = (float) min(100.0, ($points / 150) * 100);

    expect($result)->toEqual($expected);
})->repeat(100);

/**
 * Property 7: Approval Recording Completeness
 * For any TrainingRecord that is approved, the awardPoints() function SHALL set both
 * pgp_points to the awarded value and approved_at to the current timestamp.
 *
 * Validates: Requirements 4.4, 6.5
 */
test('Property 7: Approval Recording Completeness - sets both pgp_points and approved_at', function () {
    $employee = \App\Models\Hris\Employee::factory()->create();

    $record = \App\Models\Hris\TrainingRecord::factory()
        ->forEmployee($employee)
        ->completed()
        ->create([
            'pgp_points' => null,
            'approved_at' => null,
        ]);

    // Generate random points value
    $points = fake()->randomElement([35, fake()->numberBetween(15, 25)]);

    // Freeze time to avoid race conditions
    // Use startOfSecond() to remove microseconds since MySQL timestamp only stores second precision
    $frozenTime = Carbon::now()->startOfSecond();
    Carbon::setTestNow($frozenTime);

    $this->service->awardPoints($record, $points);

    // Refresh from database
    $record->refresh();

    // Verify pgp_points is set
    expect($record->pgp_points)->toBe($points);

    // Verify approved_at is set to the frozen time (comparing at second precision)
    expect($record->approved_at)->not->toBeNull();
    expect($record->approved_at->startOfSecond()->equalTo($frozenTime))->toBeTrue();

    // Reset time
    Carbon::setTestNow();
})->repeat(100);
