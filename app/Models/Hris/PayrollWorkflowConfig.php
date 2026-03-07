<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollWorkflowConfig extends Model
{
    use HasFactory;

    const MODULE_MONTHLY = 'monthly_payroll';
    const MODULE_WEEKLY = 'weekly_allowance';

    protected $fillable = [
        'module',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the approval steps for this workflow configuration.
     */
    public function steps(): HasMany
    {
        return $this->hasMany(PayrollWorkflowStep::class);
    }

    /**
     * Returns the active config for a given module, or null.
     */
    public static function getActiveConfig(string $module): ?static
    {
        return static::where('module', $module)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Scope: only active configs.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Validate that the config's steps meet the required structure:
     * - Exactly one finance step
     * - Zero or one COO steps
     *
     * @return array{valid: bool, errors: string[]}
     */
    public function validateSteps(): array
    {
        $steps = $this->steps()->get();
        $financeCount = $steps->where('step_type', PayrollWorkflowStep::STEP_FINANCE)->count();
        $cooCount = $steps->where('step_type', PayrollWorkflowStep::STEP_COO)->count();

        $errors = [];

        if ($financeCount !== 1) {
            $errors[] = "Config must have exactly 1 finance step, found {$financeCount}.";
        }

        if ($cooCount > 1) {
            $errors[] = "Config must have at most 1 COO step, found {$cooCount}.";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

}
