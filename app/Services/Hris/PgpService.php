<?php

namespace App\Services\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\TrainingRecord;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PgpService
{
    // Reward constants
    const PGP_TARGET = 150;
    const BASE_REWARD = 150000;
    const INCREMENTAL_BLOCK = 35;
    const INCREMENTAL_REWARD = 35000;

    /**
     * Get the current quarterly period boundaries.
     *
     * @return array{start: Carbon, end: Carbon, quarter: int, year: int}
     */
    public function getCurrentQuarter(): array
    {
        return $this->getQuarterForDate(Carbon::now());
    }

    /**
     * Get quarterly period for a specific date.
     *
     * @param Carbon $date
     * @return array{start: Carbon, end: Carbon, quarter: int, year: int}
     */
    public function getQuarterForDate(Carbon $date): array
    {
        $month = $date->month;
        $year = $date->year;

        // Determine quarter: Q1 (Jan-Mar), Q2 (Apr-Jun), Q3 (Jul-Sep), Q4 (Oct-Dec)
        $quarter = (int) ceil($month / 3);

        // Calculate start month of the quarter
        $startMonth = ($quarter - 1) * 3 + 1;

        $start = Carbon::create($year, $startMonth, 1)->startOfDay();
        $end = $start->copy()->addMonths(3)->subSecond();

        return [
            'start' => $start,
            'end' => $end,
            'quarter' => $quarter,
            'year' => $year,
        ];
    }

    /**
     * Calculate total PGP points for an employee in the current quarter.
     *
     * @param Employee $employee
     * @return int
     */
    public function getQuarterlyPoints(Employee $employee): int
    {
        $quarter = $this->getCurrentQuarter();

        return $this->getPointsForQuarter($employee, $quarter['quarter'], $quarter['year']);
    }

    /**
     * Calculate total PGP points for an employee in a specific quarter.
     *
     * @param Employee $employee
     * @param int $quarter (1-4)
     * @param int $year
     * @return int
     */
    public function getPointsForQuarter(Employee $employee, int $quarter, int $year): int
    {
        return TrainingRecord::where('employee_id', $employee->id)
            ->approvedInQuarter($quarter, $year)
            ->withPgpPoints()
            ->sum('pgp_points');
    }

    /**
     * Get approved training records for an employee in current quarter.
     *
     * @param Employee $employee
     * @return Collection<TrainingRecord>
     */
    public function getQuarterlyTrainingRecords(Employee $employee): Collection
    {
        $quarter = $this->getCurrentQuarter();

        return TrainingRecord::where('employee_id', $employee->id)
            ->approvedInQuarter($quarter['quarter'], $quarter['year'])
            ->withPgpPoints()
            ->get();
    }

    /**
     * Get PGP points value for a training type.
     *
     * @param string $trainingType 'free' or 'paid'
     * @param int|null $customPoints Required for 'paid' type
     * @return int
     * @throws InvalidArgumentException
     */
    public function getPointsForTrainingType(string $trainingType, ?int $customPoints = null): int
    {
        if ($trainingType === TrainingRecord::TRAINING_TYPE_FREE) {
            return TrainingRecord::PGP_FREE_TRAINING;
        }

        if ($trainingType === TrainingRecord::TRAINING_TYPE_PAID) {
            if ($customPoints === null) {
                throw new InvalidArgumentException('Custom points are required for paid training');
            }

            if (!$this->validatePaidTrainingPoints($customPoints)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Paid training points must be between %d and %d',
                        TrainingRecord::PGP_PAID_MIN,
                        TrainingRecord::PGP_PAID_MAX
                    )
                );
            }

            return $customPoints;
        }

        throw new InvalidArgumentException('Invalid training type: ' . $trainingType);
    }

    /**
     * Validate PGP points for paid training.
     *
     * @param int $points
     * @return bool
     */
    public function validatePaidTrainingPoints(int $points): bool
    {
        return $points >= TrainingRecord::PGP_PAID_MIN && $points <= TrainingRecord::PGP_PAID_MAX;
    }

    /**
     * Calculate projected reward based on PGP points.
     *
     * @param int $totalPoints
     * @return int Amount in Rupiah
     */
    public function calculateReward(int $totalPoints): int
    {
        if ($totalPoints < self::PGP_TARGET) {
            return 0;
        }

        $incrementalBlocks = (int) floor(($totalPoints - self::PGP_TARGET) / self::INCREMENTAL_BLOCK);
        $incrementalReward = $incrementalBlocks * self::INCREMENTAL_REWARD;

        return self::BASE_REWARD + $incrementalReward;
    }

    /**
     * Get progress percentage toward 150 PGP target.
     *
     * @param int $currentPoints
     * @return float (0-100, capped at 100)
     */
    public function getProgressPercentage(int $currentPoints): float
    {
        if ($currentPoints <= 0) {
            return 0.0;
        }

        $percentage = ($currentPoints / self::PGP_TARGET) * 100;

        return min(100.0, $percentage);
    }

    /**
     * Award PGP points to an employee for approved training.
     *
     * @param TrainingRecord $trainingRecord
     * @param int $points
     * @return void
     */
    public function awardPoints(TrainingRecord $trainingRecord, int $points): void
    {
        $trainingRecord->pgp_points = $points;
        $trainingRecord->approved_at = Carbon::now();
        $trainingRecord->save();
    }
}
