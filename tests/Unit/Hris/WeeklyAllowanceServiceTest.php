<?php

use App\Services\Hris\WeeklyAllowanceService;
use Carbon\Carbon;

describe('WeeklyAllowanceService Date Calculation', function () {
    it('returns dates with correct day types', function () {
        $fromDate = Carbon::parse('2026-01-28'); // Wednesday
        $dates = WeeklyAllowanceService::getPreviousWeekDates($fromDate);

        // Start should be a Monday
        expect($dates['start']->dayOfWeek)->toBe(Carbon::MONDAY);
        // End should be a Friday
        expect($dates['end']->dayOfWeek)->toBe(Carbon::FRIDAY);
        // Payment should be a Tuesday
        expect($dates['payment']->dayOfWeek)->toBe(Carbon::TUESDAY);
    });

    it('end date is 4 days after start date', function () {
        $fromDate = Carbon::parse('2026-01-27'); // Tuesday
        $dates = WeeklyAllowanceService::getPreviousWeekDates($fromDate);

        $daysDiff = (int) $dates['start']->diffInDays($dates['end']);
        expect($daysDiff)->toBe(4); // Monday to Friday = 4 days
    });

    it('payment date is after end date', function () {
        $fromDate = Carbon::parse('2026-01-26'); // Monday
        $dates = WeeklyAllowanceService::getPreviousWeekDates($fromDate);

        // Payment date should be after end date
        expect($dates['payment']->greaterThan($dates['end']))->toBeTrue();
    });
});

describe('WeeklyAllowanceService Days Counting', function () {
    it('counts present days from breakdown', function () {
        $service = app(WeeklyAllowanceService::class);

        $breakdown = [
            'mon' => true,
            'tue' => true,
            'wed' => false,
            'thu' => true,
            'fri' => true,
        ];

        expect($service->countPresentDays($breakdown))->toBe(4);
    });

    it('counts zero days when all absent', function () {
        $service = app(WeeklyAllowanceService::class);

        $breakdown = [
            'mon' => false,
            'tue' => false,
            'wed' => false,
            'thu' => false,
            'fri' => false,
        ];

        expect($service->countPresentDays($breakdown))->toBe(0);
    });

    it('counts five days when all present', function () {
        $service = app(WeeklyAllowanceService::class);

        $breakdown = [
            'mon' => true,
            'tue' => true,
            'wed' => true,
            'thu' => true,
            'fri' => true,
        ];

        expect($service->countPresentDays($breakdown))->toBe(5);
    });
});
