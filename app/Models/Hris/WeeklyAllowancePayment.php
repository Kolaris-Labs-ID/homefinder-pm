<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyAllowancePayment extends Model
{
    use HasFactory;

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_EXCLUDED = 'excluded';
    const STATUS_PAID = 'paid';

    protected $fillable = [
        'weekly_allowance_batch_id',
        'employee_id',
        'daily_rate',
        'days_present',
        'days_breakdown',
        'total_amount',
        'bank_name',
        'bank_account_number',
        'bank_account_holder',
        'has_bank_warning',
        'status',
        'hr_notes',
        'finance_notes',
    ];

    protected $casts = [
        'daily_rate' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'days_breakdown' => 'array',
        'has_bank_warning' => 'boolean',
    ];

    /**
     * Get all available statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_EXCLUDED,
            self::STATUS_PAID,
        ];
    }

    /**
     * Get status labels for display.
     */
    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_EXCLUDED => 'Excluded',
            self::STATUS_PAID => 'Paid',
        ];
    }

    // =====================
    // Relationships
    // =====================

    /**
     * Get the batch that owns this payment.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(WeeklyAllowanceBatch::class, 'weekly_allowance_batch_id');
    }

    /**
     * Get the employee that owns this payment.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
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
     * Scope to filter by employee.
     */
    public function scopeForEmployee(Builder $query, $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter by batch.
     */
    public function scopeForBatch(Builder $query, $batchId): Builder
    {
        return $query->where('weekly_allowance_batch_id', $batchId);
    }

    /**
     * Scope to get payments with bank warnings.
     */
    public function scopeWithWarnings(Builder $query): Builder
    {
        return $query->where('has_bank_warning', true);
    }

    /**
     * Scope to get paid payments.
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAID);
    }

    /**
     * Scope to get payable payments (not excluded).
     */
    public function scopePayable(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_PAID]);
    }

    // =====================
    // Accessors
    // =====================

    /**
     * Get formatted total amount.
     */
    public function getFormattedAmountAttribute(): string
    {
        return 'Rp ' . number_format((float) $this->total_amount, 0, ',', '.');
    }

    /**
     * Get formatted daily rate.
     */
    public function getFormattedDailyRateAttribute(): string
    {
        return 'Rp ' . number_format((float) $this->daily_rate, 0, ',', '.');
    }

    /**
     * Get the days breakdown as a readable string.
     */
    public function getDaysBreakdownLabelAttribute(): string
    {
        if (!$this->days_breakdown) {
            return '-';
        }

        $days = ['mon' => 'Sen', 'tue' => 'Sel', 'wed' => 'Rab', 'thu' => 'Kam', 'fri' => 'Jum'];
        $present = [];

        foreach ($days as $key => $label) {
            if ($this->days_breakdown[$key] ?? false) {
                $present[] = $label;
            }
        }

        return empty($present) ? '-' : implode(', ', $present);
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatusLabels()[$this->status] ?? $this->status;
    }

    /**
     * Check if bank info is complete.
     */
    public function hasBankInfo(): bool
    {
        return !empty($this->bank_name)
            && !empty($this->bank_account_number)
            && !empty($this->bank_account_holder);
    }

    // =====================
    // Methods
    // =====================

    /**
     * Recalculate the total amount based on days and rate.
     */
    public function recalculate(): void
    {
        $this->total_amount = (float) $this->daily_rate * $this->days_present;
        $this->save();
    }

    /**
     * Check if this payment can be edited.
     */
    public function isEditable(): bool
    {
        return $this->batch && $this->batch->isEditable();
    }

    /**
     * Refresh bank info from employee.
     */
    public function refreshBankInfo(): void
    {
        $employee = $this->employee;

        if ($employee) {
            $this->bank_name = $employee->bank_name;
            $this->bank_account_number = $employee->bank_account_number;
            $this->bank_account_holder = $employee->bank_account_holder;
            $this->has_bank_warning = !$this->hasBankInfo();
            $this->save();
        }
    }

    /**
     * Boot method to add model events.
     */
    protected static function boot()
    {
        parent::boot();

        // Prevent updates if batch is locked
        static::updating(function (WeeklyAllowancePayment $payment) {
            if ($payment->batch && $payment->batch->isLocked()) {
                throw new \Exception('Cannot modify payment for approved or paid batch.');
            }
        });

        // Prevent deletes if batch is locked
        static::deleting(function (WeeklyAllowancePayment $payment) {
            if ($payment->batch && $payment->batch->isLocked()) {
                throw new \Exception('Cannot delete payment for approved or paid batch.');
            }
        });
    }
}
