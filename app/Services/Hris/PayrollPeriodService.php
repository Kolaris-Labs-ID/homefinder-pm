<?php

namespace App\Services\Hris;

use Carbon\Carbon;
use InvalidArgumentException;

class PayrollPeriodService
{
    /**
     * The day of month when a new payroll period starts.
     */
    const CUTOFF_DAY = 26;

    /**
     * The timezone used for all date calculations.
     */
    const TIMEZONE = 'Asia/Jakarta';

    /**
     * Month names mapping.
     */
    const MONTH_NAMES = [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December',
    ];

    /**
     * Get the payroll period information for a given date.
     *
     * @param Carbon|null $date Reference date (defaults to today in Jakarta timezone)
     * @return array{name: string, start_date: Carbon, end_date: Carbon}
     */
    public function getPeriodForDate(?Carbon $date = null): array
    {
        $date = $this->normalizeDate($date);
        $periodName = $this->getPeriodName($date);

        return [
            'name' => $periodName,
            'start_date' => $this->getPeriodStartDate($periodName),
            'end_date' => $this->getPeriodEndDate($periodName),
        ];
    }

    /**
     * Get the payroll period name for a given date.
     *
     * Period naming rules:
     * - Dates 1-25: Period is named after the current month
     * - Dates 26+: Period is named after the next month
     *
     * @param Carbon|null $date Reference date
     * @return string Period name in format "MonthName YYYY"
     */
    public function getPeriodName(?Carbon $date = null): string
    {
        $date = $this->normalizeDate($date);

        $day = $date->day;
        $month = $date->month;
        $year = $date->year;

        // If day is 26 or later, the period belongs to the next month
        if ($day >= self::CUTOFF_DAY) {
            $month++;
            // Handle year transition (December 26+ → January next year)
            if ($month > 12) {
                $month = 1;
                $year++;
            }
        }

        return self::MONTH_NAMES[$month] . ' ' . $year;
    }

    /**
     * Get the start date of a payroll period.
     *
     * The start date is the 26th of the month before the period name.
     *
     * @param string $periodName Period name in format "MonthName YYYY"
     * @return Carbon
     * @throws InvalidArgumentException If period name is invalid
     */
    public function getPeriodStartDate(string $periodName): Carbon
    {
        $parsed = $this->parsePeriodName($periodName);

        // Start date is 26th of the previous month
        $startMonth = $parsed['month'] - 1;
        $startYear = $parsed['year'];

        // Handle year transition (January → December of previous year)
        if ($startMonth < 1) {
            $startMonth = 12;
            $startYear--;
        }

        return Carbon::create($startYear, $startMonth, self::CUTOFF_DAY, 0, 0, 0, self::TIMEZONE);
    }

    /**
     * Get the end date of a payroll period.
     *
     * The end date is the 25th of the month in the period name.
     *
     * @param string $periodName Period name in format "MonthName YYYY"
     * @return Carbon
     * @throws InvalidArgumentException If period name is invalid
     */
    public function getPeriodEndDate(string $periodName): Carbon
    {
        $parsed = $this->parsePeriodName($periodName);

        return Carbon::create($parsed['year'], $parsed['month'], 25, 23, 59, 59, self::TIMEZONE);
    }

    /**
     * Get the current payroll period.
     *
     * @return array{name: string, start_date: Carbon, end_date: Carbon}
     */
    public function getCurrentPeriod(): array
    {
        return $this->getPeriodForDate(Carbon::now(self::TIMEZONE));
    }

    /**
     * Parse a period name into month and year.
     *
     * @param string $periodName
     * @return array{month: int, year: int}
     * @throws InvalidArgumentException If period name is invalid
     */
    public function parsePeriodName(string $periodName): array
    {
        if (!$this->isValidPeriodName($periodName)) {
            throw new InvalidArgumentException(
                "Invalid payroll period format. Expected 'MonthName YYYY' (e.g., 'February 2025')"
            );
        }

        $parts = explode(' ', trim($periodName));
        $monthName = $parts[0];
        $year = (int) $parts[1];

        $month = array_search($monthName, self::MONTH_NAMES);

        return [
            'month' => $month,
            'year' => $year,
        ];
    }

    /**
     * Validate a period name format.
     *
     * @param string $periodName
     * @return bool
     */
    public function isValidPeriodName(string $periodName): bool
    {
        $parts = explode(' ', trim($periodName));

        if (count($parts) !== 2) {
            return false;
        }

        $monthName = $parts[0];
        $year = $parts[1];

        // Check if month name is valid
        if (!in_array($monthName, self::MONTH_NAMES)) {
            return false;
        }

        // Check if year is a valid 4-digit number
        if (!preg_match('/^\d{4}$/', $year)) {
            return false;
        }

        return true;
    }

    /**
     * Normalize a date to Jakarta timezone.
     *
     * @param Carbon|null $date
     * @return Carbon
     */
    protected function normalizeDate(?Carbon $date): Carbon
    {
        if ($date === null) {
            return Carbon::now(self::TIMEZONE);
        }

        return $date->copy()->setTimezone(self::TIMEZONE);
    }
}
