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
