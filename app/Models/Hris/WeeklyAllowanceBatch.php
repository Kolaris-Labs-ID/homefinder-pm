<?php

namespace App\Models\Hris;

use App\Contracts\Hris\PayableBatchSource;
use App\Models\Hris\Traits\HasPayrollApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WeeklyAllowanceBatch extends Model implements PayableBatchSource
{
    use HasFactory;
    use HasPayrollApproval;

    // Status constants — 5-status set (COO removed, multi-finance approver)
    const STATUS_DRAFT = 'draft';

    const STATUS_CALCULATED = 'calculated';

    const STATUS_PENDING_FINANCE = 'pending_finance';

    const STATUS_REJECTED = 'rejected';

    const STATUS_PAID = 'paid';

    // Payment-bundle lifecycle (Phase 3)
    const STATUS_ELIGIBLE_FOR_BUNDLE = 'eligible_for_bundle';

    const STATUS_IN_PAYMENT_BUNDLE = 'in_payment_bundle';

    protected $fillable = [
        'week_start_date',
        'week_end_date',
        'payment_date',
        'status',
        'payment_status',
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
        'submitted_by',
        'submitted_at',
        'total_employees',
        'employees_with_warnings',
        'total_amount',
        'notes',
        'rejection_reason',
        'current_finance_step_order',
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
        'submitted_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'current_finance_step_order' => 'integer',
    ];

    /**
     * Get all available statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_CALCULATED,
            self::STATUS_PENDING_FINANCE,
            self::STATUS_REJECTED,
            self::STATUS_PAID,
            self::STATUS_ELIGIBLE_FOR_BUNDLE,
            self::STATUS_IN_PAYMENT_BUNDLE,
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
            self::STATUS_PENDING_FINANCE => 'Pending Finance',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_PAID => 'Paid',
            self::STATUS_ELIGIBLE_FOR_BUNDLE => 'Eligible for Bundle',
            self::STATUS_IN_PAYMENT_BUNDLE => 'In Payment Bundle',
        ];
    }

    /**
     * Get status colors for badges.
     */
    public static function getStatusColors(): array
    {
        return [
            self::STATUS_DRAFT => 'gray',
            self::STATUS_CALCULATED => 'info',
            self::STATUS_PENDING_FINANCE => 'warning',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_PAID => 'success',
            self::STATUS_ELIGIBLE_FOR_BUNDLE => 'info',
            self::STATUS_IN_PAYMENT_BUNDLE => 'warning',
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

    public function paymentSourceType(): string
    {
        return PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH;
    }

    public function paymentSourceId(): string
    {
        return (string) $this->getKey();
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
        return $query->where('status', self::STATUS_PENDING_FINANCE);
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
     * Check if the batch is editable (draft or rejected status).
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED]);
    }

    /**
     * Check if the batch is locked (in approval pipeline or paid).
     */
    public function isLocked(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING_FINANCE,
            self::STATUS_PAID,
        ]);
    }

    // =====================
    // Accessors
    // =====================

    /**
     * Get the week label for display.
     */
    public function getWeekLabelAttribute(): string
    {
        return $this->week_start_date->format('d M').' - '.$this->week_end_date->format('d M Y');
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
