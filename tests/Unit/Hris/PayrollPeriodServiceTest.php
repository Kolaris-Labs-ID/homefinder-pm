<?php

use App\Services\Hris\PayrollPeriodService;
use Carbon\Carbon;

beforeEach(function () {
    $this->service = new PayrollPeriodService();
});

/**
 * Generate random dates for property-based testing.
 */
function generateRandomDates(int $count, int $minDay, int $maxDay): array
{
    $dates = [];
    for ($i = 0; $i < $count; $i++) {
        $year = rand(2020, 2030);
        $month = rand(1, 12);
        $day = rand($minDay, min($maxDay, cal_days_in_month(CAL_GREGORIAN, $month, $year)));
        $dates[] = [Carbon::create($year, $month, $day, rand(0, 23), rand(0, 59), rand(0, 59), 'Asia/Jakarta')];
    }
    return $dates;
}

/**
 * Feature: payroll-period-attendance, Property 1: Period calculation for dates 1-25
 * For any date where the day of month is between 1 and 25 (inclusive),
 * the payroll period name SHALL contain the same month name as the input date.
 *
 * **Validates: Requirements 1.1, 6.3**
 */
describe('Property 1: Period calculation for dates 1-25', function () {
    it('assigns dates 1-25 to the same month period', function (Carbon $date) {
        $periodName = $this->service->getPeriodName($date);

        $expectedMonthName = PayrollPeriodService::MONTH_NAMES[$date->month];

        expect($periodName)->toContain($expectedMonthName)
            ->and($periodName)->toContain((string) $date->year);
    })->with(generateRandomDates(100, 1, 25));
});

/**
 * Feature: payroll-period-attendance, Property 2: Period calculation for dates 26+
 * For any date where the day of month is 26 or greater,
 * the payroll period name SHALL contain the next month's name
 * (or January if the input month is December).
 *
 * **Validates: Requirements 1.2, 6.2**
 */
describe('Property 2: Period calculation for dates 26+', function () {
    it('assigns dates 26+ to the next month period', function (Carbon $date) {
        $periodName = $this->service->getPeriodName($date);

        $expectedMonth = $date->month + 1;
        $expectedYear = $date->year;

        // Handle year transition
        if ($expectedMonth > 12) {
            $expectedMonth = 1;
            $expectedYear++;
        }

        $expectedMonthName = PayrollPeriodService::MONTH_NAMES[$expectedMonth];

        expect($periodName)->toContain($expectedMonthName)
            ->and($periodName)->toContain((string) $expectedYear);
    })->with(generateRandomDates(100, 26, 31));
});

/**
 * Feature: payroll-period-attendance, Property 3: Period name format validity
 * For any valid date input, the payroll period name SHALL match the format
 * "MonthName YYYY" where MonthName is a valid English month name and YYYY is a 4-digit year.
 *
 * **Validates: Requirements 2.1, 6.1**
 */
describe('Property 3: Period name format validity', function () {
    it('returns period name in valid format for any date', function (Carbon $date) {
        $periodName = $this->service->getPeriodName($date);

        // Verify format matches "MonthName YYYY"
        expect($this->service->isValidPeriodName($periodName))->toBeTrue();

        // Verify it can be parsed back
        $parsed = $this->service->parsePeriodName($periodName);
        expect($parsed)->toHaveKeys(['month', 'year'])
            ->and($parsed['month'])->toBeGreaterThanOrEqual(1)
            ->and($parsed['month'])->toBeLessThanOrEqual(12)
            ->and($parsed['year'])->toBeGreaterThanOrEqual(2000)
            ->and($parsed['year'])->toBeLessThanOrEqual(2100);
    })->with(generateRandomDates(100, 1, 31));
});

/**
 * Feature: payroll-period-attendance, Property 4: Period date consistency
 * For any payroll period, the start date SHALL be the 26th of the month before
 * the period name, and the end date SHALL be the 25th of the month in the period name.
 *
 * **Validates: Requirements 2.2, 2.3**
 */
describe('Property 4: Period date consistency', function () {
    it('has consistent start and end dates for any period', function (Carbon $date) {
        $period = $this->service->getPeriodForDate($date);

        // Start date should be 26th
        expect($period['start_date']->day)->toBe(26);

        // End date should be 25th
        expect($period['end_date']->day)->toBe(25);

        // Parse the period name to verify month/year consistency
        $parsed = $this->service->parsePeriodName($period['name']);

        // End date month should match period name month
        expect($period['end_date']->month)->toBe($parsed['month'])
            ->and($period['end_date']->year)->toBe($parsed['year']);

        // Start date should be in the previous month
        $expectedStartMonth = $parsed['month'] - 1;
        $expectedStartYear = $parsed['year'];
        if ($expectedStartMonth < 1) {
            $expectedStartMonth = 12;
            $expectedStartYear--;
        }

        expect($period['start_date']->month)->toBe($expectedStartMonth)
            ->and($period['start_date']->year)->toBe($expectedStartYear);

        // Start date should be before end date
        expect($period['start_date']->lt($period['end_date']))->toBeTrue();
    })->with(generateRandomDates(100, 1, 31));
});


/**
 * Unit tests for year transition edge cases.
 *
 * _Requirements: 1.3, 6.4_
 */
describe('Year transition edge cases', function () {
    it('assigns December 26-31 to January of next year', function () {
        $dates = [
            Carbon::create(2024, 12, 26, 10, 0, 0, 'Asia/Jakarta'),
            Carbon::create(2024, 12, 27, 10, 0, 0, 'Asia/Jakarta'),
            Carbon::create(2024, 12, 28, 10, 0, 0, 'Asia/Jakarta'),
            Carbon::create(2024, 12, 29, 10, 0, 0, 'Asia/Jakarta'),
            Carbon::create(2024, 12, 30, 10, 0, 0, 'Asia/Jakarta'),
            Carbon::create(2024, 12, 31, 10, 0, 0, 'Asia/Jakarta'),
        ];

        foreach ($dates as $date) {
            $periodName = $this->service->getPeriodName($date);
            expect($periodName)->toBe('January 2025');
        }
    });

    it('assigns January 1-25 to January of same year', function () {
        $dates = [
            Carbon::create(2025, 1, 1, 10, 0, 0, 'Asia/Jakarta'),
            Carbon::create(2025, 1, 10, 10, 0, 0, 'Asia/Jakarta'),
            Carbon::create(2025, 1, 15, 10, 0, 0, 'Asia/Jakarta'),
            Carbon::create(2025, 1, 20, 10, 0, 0, 'Asia/Jakarta'),
            Carbon::create(2025, 1, 25, 10, 0, 0, 'Asia/Jakarta'),
        ];

        foreach ($dates as $date) {
            $periodName = $this->service->getPeriodName($date);
            expect($periodName)->toBe('January 2025');
        }
    });

    it('calculates correct period dates for January period spanning year boundary', function () {
        $period = $this->service->getPeriodForDate(Carbon::create(2025, 1, 15, 10, 0, 0, 'Asia/Jakarta'));

        expect($period['name'])->toBe('January 2025')
            ->and($period['start_date']->format('Y-m-d'))->toBe('2024-12-26')
            ->and($period['end_date']->format('Y-m-d'))->toBe('2025-01-25');
    });

    it('calculates correct period dates for December period', function () {
        $period = $this->service->getPeriodForDate(Carbon::create(2024, 12, 15, 10, 0, 0, 'Asia/Jakarta'));

        expect($period['name'])->toBe('December 2024')
            ->and($period['start_date']->format('Y-m-d'))->toBe('2024-11-26')
            ->and($period['end_date']->format('Y-m-d'))->toBe('2024-12-25');
    });

    it('handles leap year February correctly', function () {
        // February 2024 is a leap year (29 days)
        $period = $this->service->getPeriodForDate(Carbon::create(2024, 2, 29, 10, 0, 0, 'Asia/Jakarta'));

        // Feb 29 is after 26, so it belongs to March period
        expect($period['name'])->toBe('March 2024')
            ->and($period['start_date']->format('Y-m-d'))->toBe('2024-02-26')
            ->and($period['end_date']->format('Y-m-d'))->toBe('2024-03-25');
    });
});

/**
 * Additional unit tests for service methods.
 */
describe('Service helper methods', function () {
    it('validates correct period name formats', function () {
        expect($this->service->isValidPeriodName('January 2025'))->toBeTrue()
            ->and($this->service->isValidPeriodName('February 2024'))->toBeTrue()
            ->and($this->service->isValidPeriodName('December 2030'))->toBeTrue();
    });

    it('rejects invalid period name formats', function () {
        expect($this->service->isValidPeriodName('Jan 2025'))->toBeFalse()
            ->and($this->service->isValidPeriodName('January2025'))->toBeFalse()
            ->and($this->service->isValidPeriodName('January 25'))->toBeFalse()
            ->and($this->service->isValidPeriodName('2025 January'))->toBeFalse()
            ->and($this->service->isValidPeriodName(''))->toBeFalse()
            ->and($this->service->isValidPeriodName('Invalid 2025'))->toBeFalse();
    });

    it('parses valid period names correctly', function () {
        $parsed = $this->service->parsePeriodName('February 2025');

        expect($parsed['month'])->toBe(2)
            ->and($parsed['year'])->toBe(2025);
    });

    it('throws exception for invalid period name when parsing', function () {
        expect(fn() => $this->service->parsePeriodName('Invalid 2025'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('returns current period based on today', function () {
        $currentPeriod = $this->service->getCurrentPeriod();

        expect($currentPeriod)->toHaveKeys(['name', 'start_date', 'end_date'])
            ->and($this->service->isValidPeriodName($currentPeriod['name']))->toBeTrue();
    });
});
