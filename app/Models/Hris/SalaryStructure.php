<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryStructure extends Model
{
    use HasFactory;

    // PPh 21 Method constants
    const PPH21_METHOD_NETTO = 'netto';
    const PPH21_METHOD_GROSS = 'gross';
    const PPH21_METHOD_GROSS_UP = 'gross_up';

    // JKK Risk Level constants
    const JKK_RISK_VERY_LOW = 'very_low';
    const JKK_RISK_LOW = 'low';
    const JKK_RISK_MEDIUM = 'medium';
    const JKK_RISK_HIGH = 'high';
    const JKK_RISK_VERY_HIGH = 'very_high';

    // BPJS Paid By constants
    const BPJS_PAID_BY_EMPLOYEE = 'employee';
    const BPJS_PAID_BY_COMPANY = 'company';
    const BPJS_PAID_BY_SHARED = 'shared';

    protected $fillable = [
        'employee_id',
        'effective_date',
        'basic_salary',
        'allowances_fixed',
        'allowances_variable',
        'deductions',
        'pph21_method',
        'jkk_risk_level',
        'daily_allowance_rate',
        'prorate_enabled',
        'bpjs_paid_by',
        'weekly_allowance_enabled',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'basic_salary' => 'decimal:2',
        'allowances_fixed' => 'array',
        'allowances_variable' => 'array',
        'deductions' => 'array',
        'daily_allowance_rate' => 'decimal:2',
        'prorate_enabled' => 'boolean',
        'weekly_allowance_enabled' => 'boolean',
    ];

    /**
     * Get all available PPh 21 methods.
     */
    public static function getPph21Methods(): array
    {
        return [
            self::PPH21_METHOD_NETTO,
            self::PPH21_METHOD_GROSS,
            self::PPH21_METHOD_GROSS_UP,
        ];
    }

    /**
     * Get all available JKK risk levels.
     */
    public static function getJkkRiskLevels(): array
    {
        return [
            self::JKK_RISK_VERY_LOW,
            self::JKK_RISK_LOW,
            self::JKK_RISK_MEDIUM,
            self::JKK_RISK_HIGH,
            self::JKK_RISK_VERY_HIGH,
        ];
    }

    /**
     * Get all available BPJS paid by options.
     */
    public static function getBpjsPaidByOptions(): array
    {
        return [
            self::BPJS_PAID_BY_EMPLOYEE,
            self::BPJS_PAID_BY_COMPANY,
            self::BPJS_PAID_BY_SHARED,
        ];
    }

    /**
     * Get BPJS paid by labels for display.
     */
    public static function getBpjsPaidByLabels(): array
    {
        return [
            self::BPJS_PAID_BY_EMPLOYEE => 'Employee (Ditanggung Karyawan)',
            self::BPJS_PAID_BY_COMPANY => 'Company (Ditanggung Perusahaan)',
            self::BPJS_PAID_BY_SHARED => 'Shared (Ditanggung Bersama)',
        ];
    }

    /**
     * Check if BPJS is paid by company.
     */
    public function isBpjsPaidByCompany(): bool
    {
        return $this->bpjs_paid_by === self::BPJS_PAID_BY_COMPANY;
    }

    /**
     * Get the employee that owns the salary structure.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Scope to get active salary structure for a given date.
     */
    public function scopeActiveForDate(Builder $query, $date): Builder
    {
        return $query->where('effective_date', '<=', $date)
            ->orderBy('effective_date', 'desc');
    }

    /**
     * Scope to filter by employee.
     */
    public function scopeForEmployee(Builder $query, $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Calculate total fixed allowances.
     */
    public function getTotalFixedAllowancesAttribute(): float
    {
        if (empty($this->allowances_fixed)) {
            return 0;
        }

        return collect($this->allowances_fixed)->sum('amount');
    }

    /**
     * Calculate total variable allowances.
     */
    public function getTotalVariableAllowancesAttribute(): float
    {
        if (empty($this->allowances_variable)) {
            return 0;
        }

        return collect($this->allowances_variable)->sum('amount');
    }

    /**
     * Calculate total deductions.
     */
    public function getTotalDeductionsAttribute(): float
    {
        if (empty($this->deductions)) {
            return 0;
        }

        return collect($this->deductions)->sum('amount');
    }

    /**
     * Calculate gross salary (basic + all allowances).
     */
    public function getGrossSalaryAttribute(): float
    {
        return (float) $this->basic_salary
            + $this->total_fixed_allowances
            + $this->total_variable_allowances;
    }

    /**
     * Validate the 75:25 rule.
     * Basic salary must be at least 75% of (basic_salary + total_fixed_allowances).
     */
    public function validates7525Rule(): bool
    {
        $basicPlusFixed = (float) $this->basic_salary + $this->total_fixed_allowances;

        if ($basicPlusFixed <= 0) {
            return true;
        }

        $ratio = (float) $this->basic_salary / $basicPlusFixed;

        return $ratio >= 0.75;
    }

    /**
     * Get the 75:25 rule validation message.
     */
    public function get7525RuleMessage(): string
    {
        $basicPlusFixed = (float) $this->basic_salary + $this->total_fixed_allowances;
        $minBasic = $basicPlusFixed * 0.75;

        return "Basic salary must be at least 75% of (basic salary + fixed allowances). " .
            "Current: " . number_format($this->basic_salary, 0) . " / " . number_format($basicPlusFixed, 0) . " = " .
            number_format(($this->basic_salary / $basicPlusFixed) * 100, 1) . "%. " .
            "Minimum basic salary required: " . number_format($minBasic, 0);
    }
}
