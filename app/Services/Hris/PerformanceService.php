<?php

namespace App\Services\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\PerformanceCycle;
use App\Models\Hris\PerformanceGoal;
use App\Models\Hris\PerformanceReview;
use Illuminate\Support\Collection;

class PerformanceService
{
    /**
     * Activate a performance cycle.
     * Only draft cycles can be activated.
     *
     * @throws \Exception If cycle is not in draft status
     */
    public function activateCycle(PerformanceCycle $cycle): void
    {
        if ($cycle->status !== PerformanceCycle::STATUS_DRAFT) {
            throw new \Exception("Only draft cycles can be activated. Current status: {$cycle->status}");
        }

        $cycle->update([
            'status' => PerformanceCycle::STATUS_ACTIVE,
        ]);
    }

    /**
     * Close a performance cycle.
     * Only active cycles can be closed.
     *
     * @throws \Exception If cycle is not in active status
     */
    public function closeCycle(PerformanceCycle $cycle): void
    {
        if ($cycle->status !== PerformanceCycle::STATUS_ACTIVE) {
            throw new \Exception("Only active cycles can be closed. Current status: {$cycle->status}");
        }

        $cycle->update([
            'status' => PerformanceCycle::STATUS_CLOSED,
        ]);
    }

    /**
     * Check if a cycle overlaps with any existing active cycles.
     *
     * @return Collection Collection of overlapping active cycles
     */
    public function checkCycleOverlap(PerformanceCycle $cycle): Collection
    {
        return PerformanceCycle::active()
            ->where('id', '!=', $cycle->id)
            ->where(function ($query) use ($cycle) {
                $query->whereBetween('period_start_date', [$cycle->period_start_date, $cycle->period_end_date])
                    ->orWhereBetween('period_end_date', [$cycle->period_start_date, $cycle->period_end_date])
                    ->orWhere(function ($q) use ($cycle) {
                        $q->where('period_start_date', '<=', $cycle->period_start_date)
                            ->where('period_end_date', '>=', $cycle->period_end_date);
                    });
            })
            ->get();
    }


    /**
     * Validate that adding a new goal weight won't exceed 100% for an employee in a cycle.
     *
     * @param int $employeeId The employee ID
     * @param int $cycleId The cycle ID
     * @param float $newWeight The weight to add
     * @param int|null $excludeGoalId Goal ID to exclude (for updates)
     * @return bool True if valid, false if would exceed 100%
     */
    public function validateGoalWeight(int $employeeId, int $cycleId, float $newWeight, ?int $excludeGoalId = null): bool
    {
        $query = PerformanceGoal::forEmployeeInCycle($employeeId, $cycleId);

        if ($excludeGoalId !== null) {
            $query->where('id', '!=', $excludeGoalId);
        }

        $currentTotalWeight = $query->sum('weight');

        return ($currentTotalWeight + $newWeight) <= 100;
    }

    /**
     * Get the total weight of goals for an employee in a cycle.
     *
     * @param int $employeeId The employee ID
     * @param int $cycleId The cycle ID
     * @param int|null $excludeGoalId Goal ID to exclude
     * @return float Total weight
     */
    public function getTotalGoalWeight(int $employeeId, int $cycleId, ?int $excludeGoalId = null): float
    {
        $query = PerformanceGoal::forEmployeeInCycle($employeeId, $cycleId);

        if ($excludeGoalId !== null) {
            $query->where('id', '!=', $excludeGoalId);
        }

        return (float) $query->sum('weight');
    }

    /**
     * Create a new performance goal with weight validation.
     *
     * @param array $data Goal data
     * @return PerformanceGoal The created goal
     * @throws \Exception If weight validation fails or cycle is closed
     */
    public function createGoal(array $data): PerformanceGoal
    {
        $cycle = PerformanceCycle::findOrFail($data['cycle_id']);

        if ($cycle->isClosed()) {
            throw new \Exception("Cannot create goals for a closed cycle");
        }

        if (!$this->validateGoalWeight($data['employee_id'], $data['cycle_id'], $data['weight'])) {
            $currentWeight = $this->getTotalGoalWeight($data['employee_id'], $data['cycle_id']);
            throw new \Exception("Total goal weight would exceed 100%. Current: {$currentWeight}%, Attempting to add: {$data['weight']}%");
        }

        return PerformanceGoal::create([
            'employee_id' => $data['employee_id'],
            'cycle_id' => $data['cycle_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'weight' => $data['weight'],
            'target_value' => $data['target_value'],
            'actual_value' => $data['actual_value'] ?? null,
            'score' => null,
        ]);
    }

    /**
     * Update goal progress and recalculate score.
     * Formula: (actual_value / target_value) * weight
     *
     * @param PerformanceGoal $goal The goal to update
     * @param float $actualValue The actual value achieved
     * @throws \Exception If cycle is closed
     */
    public function updateGoalProgress(PerformanceGoal $goal, float $actualValue): void
    {
        $cycle = $goal->cycle;

        if ($cycle->isClosed()) {
            throw new \Exception("Cannot update goals for a closed cycle");
        }

        $goal->actual_value = $actualValue;
        $goal->score = $this->calculateGoalScore($goal);
        $goal->save();
    }

    /**
     * Calculate the score for a goal.
     * Formula: (actual_value / target_value) * weight
     *
     * @param PerformanceGoal $goal The goal
     * @return float The calculated score
     */
    public function calculateGoalScore(PerformanceGoal $goal): float
    {
        if ($goal->target_value == 0 || $goal->actual_value === null) {
            return 0;
        }

        return ($goal->actual_value / $goal->target_value) * $goal->weight;
    }


    /**
     * Calculate the overall score for an employee in a cycle.
     * The overall score is the sum of all weighted goal scores.
     *
     * @param Employee $employee The employee
     * @param PerformanceCycle $cycle The performance cycle
     * @return float The overall score
     */
    public function calculateOverallScore(Employee $employee, PerformanceCycle $cycle): float
    {
        $goals = PerformanceGoal::forEmployeeInCycle($employee->id, $cycle->id)->get();

        if ($goals->isEmpty()) {
            return 0;
        }

        return $goals->sum(function ($goal) {
            return $goal->score ?? 0;
        });
    }

    /**
     * Submit a performance review.
     * Records the submitted_at timestamp.
     *
     * @param PerformanceReview $review The review to submit
     * @throws \Exception If review is already submitted or cycle is closed
     */
    public function submitReview(PerformanceReview $review): void
    {
        if ($review->isSubmitted()) {
            throw new \Exception("Review has already been submitted");
        }

        $cycle = $review->cycle;

        if ($cycle->isClosed()) {
            throw new \Exception("Cannot submit reviews for a closed cycle");
        }

        $review->update([
            'submitted_at' => now(),
        ]);
    }

    /**
     * Create a performance review with calculated overall score.
     *
     * @param array $data Review data
     * @return PerformanceReview The created review
     * @throws \Exception If cycle is closed
     */
    public function createReview(array $data): PerformanceReview
    {
        $cycle = PerformanceCycle::findOrFail($data['cycle_id']);

        if ($cycle->isClosed()) {
            throw new \Exception("Cannot create reviews for a closed cycle");
        }

        $employee = Employee::findOrFail($data['employee_id']);

        // Calculate overall score from goals if not provided
        $overallScore = $data['overall_score'] ?? $this->calculateOverallScore($employee, $cycle);

        return PerformanceReview::create([
            'employee_id' => $data['employee_id'],
            'cycle_id' => $data['cycle_id'],
            'reviewer_id' => $data['reviewer_id'],
            'overall_score' => $overallScore,
            'comments' => $data['comments'] ?? null,
            'submitted_at' => null,
        ]);
    }
}
