<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceGoal extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'cycle_id',
        'title',
        'description',
        'weight',
        'target_value',
        'actual_value',
        'score',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'target_value' => 'decimal:2',
        'actual_value' => 'decimal:2',
        'score' => 'decimal:2',
    ];

    /**
     * Get the employee that owns the goal.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the performance cycle that owns the goal.
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycle::class, 'cycle_id');
    }

    /**
     * Calculate the score based on actual_value, target_value, and weight.
     * Formula: (actual_value / target_value) * weight
     *
     * @return float The calculated score
     */
    public function calculateScore(): float
    {
        if ($this->target_value == 0 || $this->actual_value === null) {
            return 0;
        }

        return ($this->actual_value / $this->target_value) * $this->weight;
    }

    /**
     * Update the actual value and recalculate the score.
     */
    public function updateProgress(float $actualValue): void
    {
        $this->actual_value = $actualValue;
        $this->score = $this->calculateScore();
        $this->save();
    }

    /**
     * Scope to filter by employee.
     */
    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter by cycle.
     */
    public function scopeForCycle(Builder $query, int $cycleId): Builder
    {
        return $query->where('cycle_id', $cycleId);
    }

    /**
     * Scope to filter by employee and cycle.
     */
    public function scopeForEmployeeInCycle(Builder $query, int $employeeId, int $cycleId): Builder
    {
        return $query->where('employee_id', $employeeId)->where('cycle_id', $cycleId);
    }

    /**
     * Get the progress percentage.
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->target_value == 0) {
            return 0;
        }

        return min(100, ($this->actual_value ?? 0) / $this->target_value * 100);
    }
}
