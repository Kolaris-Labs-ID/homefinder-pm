<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OvertimePayment extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_EXCLUDED = 'excluded';
    const STATUS_PAID = 'paid';

    protected $fillable = [
        'overtime_pay_batch_id',
        'employee_id',
        'total_sessions',
        'total_duration_minutes',
        'total_amount',
        'sessions_breakdown',
        'bank_name',
        'bank_account_number',
        'bank_account_holder',
        'has_bank_warning',
        'status',
        'hr_notes',
        'finance_notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'sessions_breakdown' => 'array',
        'has_bank_warning' => 'boolean',
    ];

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_EXCLUDED,
            self::STATUS_PAID,
        ];
    }

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

    public function batch(): BelongsTo
    {
        return $this->belongsTo(OvertimePayBatch::class, 'overtime_pay_batch_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function overtimeLogs(): HasMany
    {
        return $this->hasMany(OvertimeLog::class);
    }

    // =====================
    // Scopes
    // =====================

    public function scopeForEmployee(Builder $query, $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopePayable(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_PAID]);
    }

    // =====================
    // Accessors
    // =====================

    public function getFormattedAmountAttribute(): string
    {
        return 'Rp ' . number_format((float) $this->total_amount, 0, ',', '.');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::getStatusLabels()[$this->status] ?? $this->status;
    }

    public function hasBankInfo(): bool
    {
        return !empty($this->bank_name)
            && !empty($this->bank_account_number)
            && !empty($this->bank_account_holder);
    }

    public function isEditable(): bool
    {
        return $this->batch && $this->batch->isEditable();
    }

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

    // =====================
    // Boot
    // =====================

    protected static function boot()
    {
        parent::boot();

        static::updating(function (OvertimePayment $payment) {
            if ($payment->batch && $payment->batch->isLocked()) {
                throw new \Exception('Cannot modify payment for a locked batch.');
            }
        });

        static::deleting(function (OvertimePayment $payment) {
            if ($payment->batch && $payment->batch->isLocked()) {
                throw new \Exception('Cannot delete payment for a locked batch.');
            }
        });
    }
}
