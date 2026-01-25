<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'payrun_batch_id',
        'employee_id',
        'basic_salary',
        'total_fixed_allowances',
        'total_variable_allowances',
        'gross_income',
        'taxable_income',
        'bpjs_kesehatan_employee',
        'bpjs_kesehatan_company',
        'bpjs_jht_employee',
        'bpjs_jht_company',
        'bpjs_jp_employee',
        'bpjs_jp_company',
        'bpjs_jkk_company',
        'bpjs_jkm_company',
        'pph21_amount',
        'other_deductions',
        'total_deductions',
        'net_income',
        'details',
        // Prorate fields
        'total_working_days',
        'actual_days_worked',
        'prorate_ratio',
        'full_basic_salary',
        'prorated_basic_salary',
        'daily_allowance_total',
        'bpjs_company_paid',
        // Weekly allowance reconciliation
        'weekly_allowance_earned',
        'weekly_allowance_paid',
        'weekly_allowance_adjustment',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'total_fixed_allowances' => 'decimal:2',
        'total_variable_allowances' => 'decimal:2',
        'gross_income' => 'decimal:2',
        'taxable_income' => 'decimal:2',
        'bpjs_kesehatan_employee' => 'decimal:2',
        'bpjs_kesehatan_company' => 'decimal:2',
        'bpjs_jht_employee' => 'decimal:2',
        'bpjs_jht_company' => 'decimal:2',
        'bpjs_jp_employee' => 'decimal:2',
        'bpjs_jp_company' => 'decimal:2',
        'bpjs_jkk_company' => 'decimal:2',
        'bpjs_jkm_company' => 'decimal:2',
        'pph21_amount' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_income' => 'decimal:2',
        'details' => 'array',
        // Prorate field casts
        'prorate_ratio' => 'decimal:4',
        'full_basic_salary' => 'decimal:2',
        'prorated_basic_salary' => 'decimal:2',
        'daily_allowance_total' => 'decimal:2',
        'bpjs_company_paid' => 'boolean',
        // Weekly allowance reconciliation
        'weekly_allowance_earned' => 'decimal:2',
        'weekly_allowance_paid' => 'decimal:2',
        'weekly_allowance_adjustment' => 'decimal:2',
    ];

    /**
     * Get the payrun batch that owns this record.
     */
    public function payrunBatch(): BelongsTo
    {
        return $this->belongsTo(PayrunBatch::class);
    }

    /**
     * Get the employee that owns this record.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Scope to filter by employee.
     */
    public function scopeForEmployee(Builder $query, $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter by payrun batch.
     */
    public function scopeForBatch(Builder $query, $batchId): Builder
    {
        return $query->where('payrun_batch_id', $batchId);
    }

    /**
     * Get total employee BPJS contributions.
     */
    public function getTotalBpjsEmployeeAttribute(): float
    {
        return (float) $this->bpjs_kesehatan_employee
            + (float) $this->bpjs_jht_employee
            + (float) $this->bpjs_jp_employee;
    }

    /**
     * Get total company BPJS contributions.
     */
    public function getTotalBpjsCompanyAttribute(): float
    {
        return (float) $this->bpjs_kesehatan_company
            + (float) $this->bpjs_jht_company
            + (float) $this->bpjs_jp_company
            + (float) $this->bpjs_jkk_company
            + (float) $this->bpjs_jkm_company;
    }

    /**
     * Check if the record can be modified.
     */
    public function isEditable(): bool
    {
        return $this->payrunBatch && !$this->payrunBatch->isLocked();
    }

    /**
     * Get the prorate label for display.
     * Example: "15/21 HK" (15 out of 21 working days)
     */
    public function getProrateLabelAttribute(): string
    {
        if ($this->total_working_days === null || $this->actual_days_worked === null) {
            return '-';
        }

        return "{$this->actual_days_worked}/{$this->total_working_days} HK";
    }

    /**
     * Get the prorate percentage.
     */
    public function getProratePercentageAttribute(): float
    {
        if ($this->prorate_ratio === null) {
            return 100.0;
        }

        return round((float) $this->prorate_ratio * 100, 2);
    }

    /**
     * Check if this payroll is prorated.
     */
    public function isProrated(): bool
    {
        return $this->prorate_ratio !== null && (float) $this->prorate_ratio < 1.0;
    }

    /**
     * Boot method to add model events.
     */
    protected static function boot()
    {
        parent::boot();

        // Prevent updates if batch is locked
        static::updating(function (PayrollRecord $record) {
            if ($record->payrunBatch && $record->payrunBatch->isLocked()) {
                throw new \Exception('Cannot modify payroll record for approved or paid batch.');
            }
        });

        // Prevent deletes if batch is locked
        static::deleting(function (PayrollRecord $record) {
            if ($record->payrunBatch && $record->payrunBatch->isLocked()) {
                throw new \Exception('Cannot delete payroll record for approved or paid batch.');
            }
        });
    }
}
