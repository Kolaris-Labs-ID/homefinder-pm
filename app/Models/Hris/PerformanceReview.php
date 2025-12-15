<?php

namespace App\Models\Hris;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'cycle_id',
        'reviewer_id',
        'overall_score',
        'comments',
        'submitted_at',
    ];

    protected $casts = [
        'overall_score' => 'decimal:2',
        'submitted_at' => 'datetime',
    ];

    /**
     * Get the employee being reviewed.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the performance cycle for this review.
     */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceCycle::class, 'cycle_id');
    }

    /**
     * Get the reviewer (user) who conducted the review.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * Check if the review has been submitted.
     */
    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
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
     * Scope to filter by reviewer.
     */
    public function scopeByReviewer(Builder $query, int $reviewerId): Builder
    {
        return $query->where('reviewer_id', $reviewerId);
    }

    /**
     * Scope to filter submitted reviews.
     */
    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->whereNotNull('submitted_at');
    }

    /**
     * Scope to filter pending (not submitted) reviews.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('submitted_at');
    }
}
