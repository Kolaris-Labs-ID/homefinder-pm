<?php

namespace App\Models\Hris;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrunBatch extends Model
{
    use HasFactory;

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_CALCULATED = 'calculated';
    const STATUS_APPROVED = 'approved';
    const STATUS_PAID = 'paid';

    protected $fillable = [
        'period_start_date',
        'period_end_date',
        'pay_date',
        'status',
        'notes',
        'calculated_at',
        'approved_at',
        'approved_by',
        'paid_at',
        'paid_by',
    ];

    protected $casts = [
        'period_start_date' => 'date',
        'period_end_date' => 'date',
        'pay_date' => 'date',
        'calculated_at' => 'datetime',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    /**
     * Get all available statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_CALCULATED,
            self::STATUS_APPROVED,
            self::STATUS_PAID,
        ];
    }

    /**
     * Get status labels for display.
     */
    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_CALCULATED => 'Calculated',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_PAID => 'Paid',
        ];
    }

    /**
     * Get the payroll records for this batch.
     */
    public function payrollRecords(): HasMany
    {
        return $this->hasMany(PayrollRecord::class);
    }

    /**
     * Get the user who approved this batch.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who marked this batch as paid.
     */
    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * Scope to filter by status.
     */
    public function scopeForStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get draft batches.
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Scope to get calculated batches.
     */
    public function scopeCalculated(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CALCULATED);
    }

    /**
     * Scope to get approved batches.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope to get paid batches.
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAID);
    }

    /**
     * Check if the batch is editable (draft status).
     */
    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if the batch can be calculated.
     */
    public function canCalculate(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if the batch can be approved.
     */
    public function canApprove(): bool
    {
        return $this->status === self::STATUS_CALCULATED;
    }

    /**
     * Check if the batch can be marked as paid.
     */
    public function canMarkAsPaid(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if the batch is locked (approved or paid).
     */
    public function isLocked(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_PAID]);
    }

    /**
     * Get the period label for display.
     */
    public function getPeriodLabelAttribute(): string
    {
        return $this->period_start_date->format('d M Y') . ' - ' . $this->period_end_date->format('d M Y');
    }

    /**
     * Get the employee count for this batch.
     */
    public function getEmployeeCountAttribute(): int
    {
        return $this->payrollRecords()->count();
    }

    /**
     * Get the total net income for this batch.
     */
    public function getTotalNetIncomeAttribute(): float
    {
        return $this->payrollRecords()->sum('net_income');
    }

    /**
     * Get the total gross income for this batch.
     */
    public function getTotalGrossIncomeAttribute(): float
    {
        return $this->payrollRecords()->sum('gross_income');
    }
}
