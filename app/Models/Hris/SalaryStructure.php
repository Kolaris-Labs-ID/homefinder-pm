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

    // Statutory divisor for the overtime hourly rate (Indonesian labour law)
    const OVERTIME_HOURS_PER_MONTH = 173;

    // Default BPJS config when none is set
    const DEFAULT_BPJS_CONFIG = [
        'kesehatan' => ['enabled' => true, 'paid_by' => 'employee'],
        'jht' => ['enabled' => true, 'paid_by' => 'employee'],
        'jp' => ['enabled' => true, 'paid_by' => 'employee'],
        'jkk' => ['enabled' => true],
        'jkm' => ['enabled' => true],
    ];

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
        'bpjs_enabled',
        'bpjs_config',
        'bpjs_paid_by',
        'weekly_allowance_enabled',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'basic_salary' => 'decimal:2',
        'allowances_fixed' => 'array',
        'allowances_variable' => 'array',
        'deductions' => 'array',
        'bpjs_config' => 'array',
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
     * Check if BPJS master toggle is enabled.
     */
    public function isBpjsEnabled(): bool
    {
        return (bool) $this->bpjs_enabled;
    }

    /**
     * Get the resolved BPJS config (merged with defaults).
     */
    public function getBpjsConfig(): array
    {
        $config = $this->bpjs_config ?? [];

        return array_replace_recursive(self::DEFAULT_BPJS_CONFIG, $config);
    }

    /**
     * Check if a specific BPJS component is enabled.
     * Returns false if master toggle is off OR component is disabled.
     */
    public function isBpjsComponentEnabled(string $component): bool
    {
        if (! $this->isBpjsEnabled()) {
            return false;
        }

        $config = $this->getBpjsConfig();

        return $config[$component]['enabled'] ?? false;
    }

    /**
     * Get paid_by for a specific BPJS component.
     * Falls back to legacy bpjs_paid_by if not set in config.
     */
    public function getBpjsComponentPaidBy(string $component): string
    {
        $config = $this->getBpjsConfig();

        return $config[$component]['paid_by'] ?? $this->bpjs_paid_by ?? self::BPJS_PAID_BY_EMPLOYEE;
    }

    /**
     * Check if BPJS is paid by company (legacy, uses global bpjs_paid_by).
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
     * Statutory overtime hourly base: monthly basic salary / 173.
     *
     * Fixed allowances are deliberately excluded — see OvertimePayService.
     */
    public function getOvertimeHourlyRateAttribute(): float
    {
        return round((float) $this->basic_salary / self::OVERTIME_HOURS_PER_MONTH, 2);
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

        return 'Basic salary must be at least 75% of (basic salary + fixed allowances). '.
            'Current: '.number_format($this->basic_salary, 0).' / '.number_format($basicPlusFixed, 0).' = '.
            number_format(($this->basic_salary / $basicPlusFixed) * 100, 1).'%. '.
            'Minimum basic salary required: '.number_format($minBasic, 0);
    }
}
