<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LatePayCompensationItem extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_EXCLUDED = 'excluded';

    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'late_pay_compensation_batch_id',
        'employee_id',
        'basic_salary_snapshot',
        'days_late',
        'rate_percent',
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
        'basic_salary_snapshot' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'rate_percent' => 'decimal:2',
        'has_bank_warning' => 'boolean',
        'days_late' => 'integer',
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

    public function batch(): BelongsTo
    {
        return $this->belongsTo(LatePayCompensationBatch::class, 'late_pay_compensation_batch_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function partialPayments(): HasMany
    {
        return $this->hasMany(LatePayCompensationPartialPayment::class, 'late_pay_compensation_item_id');
    }

    public function totalPaidPercent(): float
    {
        return (float) $this->partialPayments()->sum('paid_percent');
    }

    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function getFormattedAmountAttribute(): string
    {
        return 'Rp '.number_format((float) $this->total_amount, 0, ',', '.');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::getStatusLabels()[$this->status] ?? $this->status;
    }

    public function hasBankInfo(): bool
    {
        return ! empty($this->bank_name)
            && ! empty($this->bank_account_number)
            && ! empty($this->bank_account_holder);
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
            $this->has_bank_warning = ! $this->hasBankInfo();
            $this->save();
        }
    }

    protected static function boot(): void
    {
        parent::boot();

        static::updating(function (LatePayCompensationItem $item) {
            if ($item->batch && $item->batch->isLocked()) {
                throw new \Exception('Cannot modify item for a locked batch.');
            }
        });

        static::deleting(function (LatePayCompensationItem $item) {
            if ($item->batch && $item->batch->isLocked()) {
                throw new \Exception('Cannot delete item for a locked batch.');
            }
        });
    }
}
