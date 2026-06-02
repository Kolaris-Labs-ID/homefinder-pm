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

class LatePayCompensationBatch extends Model implements PayableBatchSource
{
    use HasFactory;
    use HasPayrollApproval;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_CALCULATED = 'calculated';

    public const STATUS_PENDING_FINANCE = 'pending_finance';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'scheduled_pay_date',
        'actual_pay_date',
        'compensation_transfer_date',
        'days_late',
        'rate_percent',
        'status',
        'payment_status',
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
        'scheduled_pay_date' => 'date',
        'actual_pay_date' => 'date',
        'compensation_transfer_date' => 'date',
        'generated_at' => 'datetime',
        'submitted_at' => 'datetime',
        'finance_approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'rate_percent' => 'decimal:2',
        'current_finance_step_order' => 'integer',
        'days_late' => 'integer',
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

    public function items(): HasMany
    {
        return $this->hasMany(LatePayCompensationItem::class);
    }

    public function paymentSourceType(): string
    {
        return PaymentBundleItem::SOURCE_LATE_PAY_COMPENSATION_BATCH;
    }

    public function paymentSourceId(): string
    {
        return (string) $this->getKey();
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

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

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_CALCULATED, self::STATUS_REJECTED], true);
    }

    public function isLocked(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING_FINANCE,
            self::STATUS_PAID,
        ], true);
    }

    public function getWorkflowModule(): string
    {
        return PayrollWorkflowConfig::MODULE_LATE_PAY_COMPENSATION;
    }

    public function getPeriodLabelAttribute(): string
    {
        return $this->scheduled_pay_date->format('d M Y')
            .' → '.$this->actual_pay_date->format('d M Y');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::getStatusLabels()[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::getStatusColors()[$this->status] ?? 'gray';
    }

    public function recalculateSummary(): void
    {
        $this->total_employees = $this->items()->count();
        $this->employees_with_warnings = $this->items()->where('has_bank_warning', true)->count();
        $this->total_amount = $this->items()
            ->whereIn('status', ['pending', 'approved', 'paid'])
            ->sum('total_amount');
        $this->save();
    }
}
