<?php

namespace App\Models\Hris;

use App\Services\Hris\PayrollPeriodService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    use HasFactory;

    // Status constants
    const STATUS_PRESENT = 'present';

    const STATUS_LATE = 'late';

    const STATUS_HALF_DAY = 'half_day';

    const STATUS_ABSENT = 'absent';

    const STATUS_LEAVE = 'leave';

    const STATUS_HOLIDAY = 'holiday';

    const STATUS_VISIT = 'visit';

    // Mode constants
    const MODE_OFFICE = 'office';

    const MODE_WFH = 'wfh';

    const MODE_WFA = 'wfa';

    // Source constants
    const SOURCE_WEB = 'web';

    const SOURCE_MOBILE = 'mobile';

    const SOURCE_BIOMETRIC = 'biometric';

    const SOURCE_IMPORT = 'import';

    const SOURCE_VISIT = 'visit';

    protected $fillable = [
        'employee_id',
        'client_visit_id',
        'date',
        'mode',
        'check_in_time',
        'check_in_latitude',
        'check_in_longitude',
        'check_in_address',
        'check_in_photo_path',
        'check_out_time',
        'check_out_latitude',
        'check_out_longitude',
        'check_out_address',
        'check_out_photo_path',
        'break_start_time',
        'break_end_time',
        'total_break_minutes',
        'source',
        'status',
        'metadata',
        'payroll_period_name',
        'payroll_period_start',
        'payroll_period_end',
        'missing_checkout',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'check_in_time' => 'datetime:Y-m-d H:i:s',
        'check_out_time' => 'datetime:Y-m-d H:i:s',
        'break_start_time' => 'datetime:Y-m-d H:i:s',
        'break_end_time' => 'datetime:Y-m-d H:i:s',
        'check_in_latitude' => 'decimal:8',
        'check_in_longitude' => 'decimal:8',
        'check_out_latitude' => 'decimal:8',
        'check_out_longitude' => 'decimal:8',
        'metadata' => 'array',
        'payroll_period_start' => 'date:Y-m-d',
        'payroll_period_end' => 'date:Y-m-d',
        'missing_checkout' => 'boolean',
    ];

    /**
     * Boot the model.
     *
     * Auto-populate payroll period fields when creating new records.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (AttendanceLog $attendanceLog) {
            // Only auto-populate if date is set and payroll period fields are not already set
            if ($attendanceLog->date && ! $attendanceLog->payroll_period_name) {
                $payrollPeriodService = app(PayrollPeriodService::class);
                $period = $payrollPeriodService->getPeriodForDate($attendanceLog->date);

                $attendanceLog->payroll_period_name = $period['name'];
                $attendanceLog->payroll_period_start = $period['start_date'];
                $attendanceLog->payroll_period_end = $period['end_date'];
            }
        });
    }

    /**
     * Get all available statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PRESENT,
            self::STATUS_LATE,
            self::STATUS_HALF_DAY,
            self::STATUS_ABSENT,
            self::STATUS_LEAVE,
            self::STATUS_HOLIDAY,
            self::STATUS_VISIT,
        ];
    }

    /**
     * Get all available modes.
     */
    public static function getModes(): array
    {
        return [
            self::MODE_OFFICE,
            self::MODE_WFH,
        ];
    }

    /**
     * Modes that consume the shared weekly remote-work quota.
     */
    public static function getQuotaModes(): array
    {
        return [
            self::MODE_WFH,
            self::MODE_WFA,
        ];
    }

    /**
     * Get all available sources.
     */
    public static function getSources(): array
    {
        return [
            self::SOURCE_WEB,
            self::SOURCE_MOBILE,
            self::SOURCE_BIOMETRIC,
            self::SOURCE_IMPORT,
            self::SOURCE_VISIT,
        ];
    }

    /**
     * Get the employee that owns the attendance log.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the client visit associated with this attendance log (if source is 'visit').
     */
    public function clientVisit(): BelongsTo
    {
        return $this->belongsTo(ClientVisit::class);
    }

    /**
     * Scope a query to filter by date.
     */
    public function scopeForDate(Builder $query, $date): Builder
    {
        return $query->whereDate('date', $date);
    }

    /**
     * Scope a query to filter by employee.
     */
    public function scopeForEmployee(Builder $query, $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeForStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to filter by mode.
     */
    public function scopeForMode(Builder $query, string $mode): Builder
    {
        return $query->where('mode', $mode);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeForDateRange(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('date', [$from, $to]);
    }

    /**
     * Scope a query to filter by payroll period name.
     *
     * @param  string  $periodName  Period name in format "MonthName YYYY"
     */
    public function scopeForPayrollPeriod(Builder $query, string $periodName): Builder
    {
        return $query->where('payroll_period_name', $periodName);
    }

    /**
     * Check if the employee has checked in.
     */
    public function hasCheckedIn(): bool
    {
        return $this->check_in_time !== null;
    }

    /**
     * Check if the employee has checked out.
     */
    public function hasCheckedOut(): bool
    {
        return $this->check_out_time !== null;
    }

    /**
     * Check if the employee is currently on break.
     */
    public function isOnBreak(): bool
    {
        return $this->break_start_time !== null && $this->break_end_time === null;
    }

    /**
     * Check if the employee has completed their break.
     */
    public function hasCompletedBreak(): bool
    {
        return $this->break_start_time !== null && $this->break_end_time !== null;
    }

    /**
     * Scope to filter records with missing checkout.
     */
    public function scopeMissingCheckout(Builder $query): Builder
    {
        return $query->where('missing_checkout', true);
    }

    /**
     * Check if this record has a missing checkout flag.
     */
    public function isMissingCheckout(): bool
    {
        return (bool) $this->missing_checkout;
    }
}
