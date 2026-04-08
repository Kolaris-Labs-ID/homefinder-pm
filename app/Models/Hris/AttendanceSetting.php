<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_start_time',
        'late_grace_period_minutes',
        'wfh_quota_per_week',
        'full_day_hours',
        'half_day_hours',
        'late_penalty_tiers',
    ];

    protected $casts = [
        'work_start_time' => 'string',
        'late_grace_period_minutes' => 'integer',
        'wfh_quota_per_week' => 'integer',
        'full_day_hours' => 'decimal:2',
        'half_day_hours' => 'decimal:2',
        'late_penalty_tiers' => 'array',
    ];

    /**
     * Default values matching current hardcoded constants in AttendanceService.
     */
    const DEFAULTS = [
        'work_start_time' => '09:00',
        'late_grace_period_minutes' => 15,
        'wfh_quota_per_week' => 1,
        'full_day_hours' => 8,
        'half_day_hours' => 4,
        'late_penalty_tiers' => [
            ['minutes_late' => 40,  'deduction_percent' => 25],
            ['minutes_late' => 120, 'deduction_percent' => 50],
            ['minutes_late' => 240, 'deduction_percent' => 100],
        ],
    ];

    /**
     * Returns the singleton settings row, creating with defaults if missing.
     */
    public static function getSettings(): static
    {
        return static::firstOrCreate([], static::DEFAULTS);
    }
}
