<?php

namespace App\Models\Hris;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WeeklyAllowanceBatch extends Model
{
    use HasFactory;

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING_HR = 'pending_hr';
    const STATUS_PENDING_FINANCE = 'pending_finance';
    const STATUS_PENDING_FINAL = 'pending_final';
    const STATUS_APPROVED = 'approved';
    const STATUS_PAID = 'paid';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'week_start_date',
        'week_end_date',
        'payment_date',
        'status',
        'generated_by',
        'generated_at',
        'hr_reviewed_by',
        'hr_reviewed_at',
        'finance_approved_by',
        'finance_approved_at',
        'final_approved_by',
        'final_approved_at',
        'paid_by',
        'paid_at',
        'total_employees',
        'employees_with_warnings',
        'total_amount',
        'notes',
        'rejection_reason',
    ];

    protected $casts = [
        'week_start_date' => 'date',
        'week_end_date' => 'date',
        'payment_date' => 'date',
        'generated_at' => 'datetime',
        'hr_reviewed_at' => 'datetime',
        'finance_approved_at' => 'datetime',
        'final_approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Get all available statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PENDING_HR,
            self::STATUS_PENDING_FINANCE,
            self::STATUS_PENDING_FINAL,
            self::STATUS_APPROVED,
            self::STATUS_PAID,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * Get status labels for display.
     */
    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING_HR => 'Pending HR Review',
            self::STATUS_PENDING_FINANCE => 'Pending Finance',
            self::STATUS_PENDING_FINAL => 'Pending Final Approval',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_PAID => 'Paid',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    /**
     * Get status colors for badges.
     */
    public static function getStatusColors(): array
    {
        return [
            self::STATUS_DRAFT => 'gray',
            self::STATUS_PENDING_HR => 'warning',
            self::STATUS_PENDING_FINANCE => 'info',
            self::STATUS_PENDING_FINAL => 'primary',
            self::STATUS_APPROVED => 'success',
            self::STATUS_PAID => 'success',
            self::STATUS_CANCELLED => 'danger',
        ];
    }

    // =====================
    // Relationships
    // =====================

    /**
     * Get the payments for this batch.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(WeeklyAllowancePayment::class);
    }

    /**
     * Get the user who generated this batch.
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Get the user who reviewed this batch (HR).
     */
    public function hrReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_reviewed_by');
    }

    /**
     * Get the user who approved this batch (Finance).
     */
    public function financeApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_approved_by');
    }

    /**
     * Get the user who gave final approval (COO).
     */
    public function finalApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'final_approved_by');
    }

    /**
     * Get the user who marked this batch as paid.
     */
    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    // =====================
    // Scopes
    // =====================

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
     * Scope to get pending batches (any pending status).
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING_HR,
            self::STATUS_PENDING_FINANCE,
            self::STATUS_PENDING_FINAL,
        ]);
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
     * Scope to filter by week.
     */
    public function scopeForWeek(Builder $query, $weekStart): Builder
    {
        return $query->where('week_start_date', $weekStart);
    }

    /**
     * Scope to get batches paid within a date range.
     */
    public function scopePaidBetween(Builder $query, $startDate, $endDate): Builder
    {
        return $query->where('status', self::STATUS_PAID)
            ->whereBetween('paid_at', [$startDate, $endDate]);
    }

    // =====================
    // Workflow Helpers
    // =====================

    /**
     * Check if the batch is editable (draft status).
     */
    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if the batch can be submitted to HR.
     */
    public function canSubmitToHr(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if the batch can be submitted to Finance.
     */
    public function canSubmitToFinance(): bool
    {
        return $this->status === self::STATUS_PENDING_HR;
    }

    /**
     * Check if the batch can be approved by Finance.
     */
    public function canApproveByFinance(): bool
    {
        return $this->status === self::STATUS_PENDING_FINANCE;
    }

    /**
     * Check if the batch can be given final approval.
     */
    public function canApproveFinal(): bool
    {
        return $this->status === self::STATUS_PENDING_FINAL;
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
     * Check if the batch can be cancelled.
     */
    public function canCancel(): bool
    {
        return !in_array($this->status, [self::STATUS_PAID, self::STATUS_CANCELLED]);
    }

    // =====================
    // Accessors
    // =====================

    /**
     * Get the week label for display.
     */
    public function getWeekLabelAttribute(): string
    {
        return $this->week_start_date->format('d M') . ' - ' . $this->week_end_date->format('d M Y');
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatusLabels()[$this->status] ?? $this->status;
    }

    /**
     * Get the status color.
     */
    public function getStatusColorAttribute(): string
    {
        return self::getStatusColors()[$this->status] ?? 'gray';
    }

    /**
     * Get count of payments with warnings.
     */
    public function getWarningCountAttribute(): int
    {
        return $this->payments()->where('has_bank_warning', true)->count();
    }

    /**
     * Get approved payments count.
     */
    public function getApprovedPaymentsCountAttribute(): int
    {
        return $this->payments()->where('status', 'approved')->count();
    }

    /**
     * Get approved payments total.
     */
    public function getApprovedPaymentsTotalAttribute(): float
    {
        return (float) $this->payments()->where('status', 'approved')->sum('total_amount');
    }

    // =====================
    // Methods
    // =====================

    /**
     * Recalculate summary fields.
     */
    public function recalculateSummary(): void
    {
        $this->total_employees = $this->payments()->count();
        $this->employees_with_warnings = $this->payments()->where('has_bank_warning', true)->count();
        $this->total_amount = $this->payments()->whereIn('status', ['pending', 'approved', 'paid'])->sum('total_amount');
        $this->save();
    }
}
