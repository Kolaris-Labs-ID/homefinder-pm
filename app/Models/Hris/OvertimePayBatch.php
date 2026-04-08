<?php

namespace App\Models\Hris;

use App\Models\Hris\Traits\HasPayrollApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OvertimePayBatch extends Model
{
    use HasFactory;
    use HasPayrollApproval;

    const STATUS_DRAFT = 'draft';
    const STATUS_CALCULATED = 'calculated';
    const STATUS_PENDING_FINANCE = 'pending_finance';
    const STATUS_REJECTED = 'rejected';
    const STATUS_PAID = 'paid';

    protected $fillable = [
        'week_start_date',
        'week_end_date',
        'payment_date',
        'status',
        'generated_by',
        'generated_at',
        'submitted_by',
        'submitted_at',
        'finance_approved_by',
        'finance_approved_at',
        'paid_by',
        'paid_at',
        'total_employees',
        'employees_with_warnings',
        'total_amount',
        'current_finance_step_order',
        'notes',
        'rejection_reason',
    ];

    protected $casts = [
        'week_start_date' => 'date',
        'week_end_date' => 'date',
        'payment_date' => 'date',
        'generated_at' => 'datetime',
        'submitted_at' => 'datetime',
        'finance_approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'current_finance_step_order' => 'integer',
    ];

    public static function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_CALCULATED,
            self::STATUS_PENDING_FINANCE,
            self::STATUS_REJECTED,
            self::STATUS_PAID,
        ];
    }

    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_CALCULATED => 'Calculated',
            self::STATUS_PENDING_FINANCE => 'Pending Finance',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_PAID => 'Paid',
        ];
    }

    public static function getStatusColors(): array
    {
        return [
            self::STATUS_DRAFT => 'gray',
            self::STATUS_CALCULATED => 'info',
            self::STATUS_PENDING_FINANCE => 'warning',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_PAID => 'success',
        ];
    }

    // =====================
    // Relationships
    // =====================

    public function payments(): HasMany
    {
        return $this->hasMany(OvertimePayment::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    // =====================
    // Scopes
    // =====================

    public function scopeForStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING_FINANCE);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeForWeek(Builder $query, $weekStart): Builder
    {
        return $query->where('week_start_date', $weekStart);
    }

    // =====================
    // Workflow Helpers
    // =====================

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED]);
    }

    public function isLocked(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING_FINANCE,
            self::STATUS_PAID,
        ]);
    }

    // =====================
    // HasPayrollApproval override
    // =====================

    public function getWorkflowModule(): string
    {
        return PayrollWorkflowConfig::MODULE_OVERTIME;
    }

    // =====================
    // Accessors
    // =====================

    public function getWeekLabelAttribute(): string
    {
        return $this->week_start_date->format('d M') . ' - ' . $this->week_end_date->format('d M Y');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::getStatusLabels()[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::getStatusColors()[$this->status] ?? 'gray';
    }

    // =====================
    // Methods
    // =====================

    public function recalculateSummary(): void
    {
        $this->total_employees = $this->payments()->count();
        $this->employees_with_warnings = $this->payments()->where('has_bank_warning', true)->count();
        $this->total_amount = $this->payments()->whereIn('status', ['pending', 'approved', 'paid'])->sum('total_amount');
        $this->save();
    }
}
