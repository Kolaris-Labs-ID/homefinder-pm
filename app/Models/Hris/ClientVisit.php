<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientVisit extends Model
{
    use HasFactory;

    // Visit type constants
    const TYPE_PLANNED = 'planned';
    const TYPE_SUDDEN = 'sudden';

    // Purpose constants
    const PURPOSE_ACCOUNTING_TRAINING = 'accounting_training';
    const PURPOSE_TAX_CONSULTATION = 'tax_consultation';
    const PURPOSE_COMPANY_ESTABLISHMENT = 'company_establishment';
    const PURPOSE_LEGAL_PERMITS = 'legal_permits';
    const PURPOSE_BUSINESS_PERMITS = 'business_permits';
    const PURPOSE_AUDIT = 'audit';
    const PURPOSE_OTHER = 'other';

    // Status constants
    const STATUS_PLANNED = 'planned';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'employee_id',
        'client_name',
        'client_address',
        'latitude',
        'longitude',
        'purpose',
        'purpose_detail',
        'visit_date',
        'planned_start_time',
        'planned_end_time',
        'actual_check_in',
        'actual_check_out',
        'check_in_latitude',
        'check_in_longitude',
        'check_out_latitude',
        'check_out_longitude',
        'notes',
        'visit_report',
        'photo_path',
        'status',
        'visit_type',
        'cancellation_reason',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'actual_check_in' => 'datetime',
        'actual_check_out' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'check_in_latitude' => 'decimal:8',
        'check_in_longitude' => 'decimal:8',
        'check_out_latitude' => 'decimal:8',
        'check_out_longitude' => 'decimal:8',
    ];

    /**
     * Get all available purposes.
     */
    public static function getPurposes(): array
    {
        return [
            self::PURPOSE_ACCOUNTING_TRAINING,
            self::PURPOSE_TAX_CONSULTATION,
            self::PURPOSE_COMPANY_ESTABLISHMENT,
            self::PURPOSE_LEGAL_PERMITS,
            self::PURPOSE_BUSINESS_PERMITS,
            self::PURPOSE_AUDIT,
            self::PURPOSE_OTHER,
        ];
    }

    /**
     * Get purpose labels for display.
     */
    public static function getPurposeLabels(): array
    {
        return [
            self::PURPOSE_ACCOUNTING_TRAINING => 'Accounting Training',
            self::PURPOSE_TAX_CONSULTATION => 'Tax Consultation',
            self::PURPOSE_COMPANY_ESTABLISHMENT => 'Company Establishment',
            self::PURPOSE_LEGAL_PERMITS => 'Legal Permits',
            self::PURPOSE_BUSINESS_PERMITS => 'Business Permits',
            self::PURPOSE_AUDIT => 'Audit',
            self::PURPOSE_OTHER => 'Other',
        ];
    }

    /**
     * Get all available statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PLANNED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * Get status labels for display.
     */
    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_PLANNED => 'Planned',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    /**
     * Get visit type labels for display.
     */
    public static function getVisitTypeLabels(): array
    {
        return [
            self::TYPE_PLANNED => 'Planned',
            self::TYPE_SUDDEN => 'Sudden',
        ];
    }

    /**
     * Get the employee that owns this visit.
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
     * Scope to filter by status.
     */
    public function scopeForStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get planned visits.
     */
    public function scopePlanned(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PLANNED);
    }

    /**
     * Scope to get today's visits.
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('visit_date', today());
    }

    /**
     * Scope to get upcoming visits.
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('visit_date', '>=', today())
            ->whereIn('status', [self::STATUS_PLANNED, self::STATUS_IN_PROGRESS]);
    }

    /**
     * Check if the visit can be checked in.
     */
    public function canCheckIn(): bool
    {
        return $this->status === self::STATUS_PLANNED
            && $this->visit_date->isToday();
    }

    /**
     * Check if the visit can be checked out.
     */
    public function canCheckOut(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Check if the visit can be cancelled.
     */
    public function canCancel(): bool
    {
        return in_array($this->status, [self::STATUS_PLANNED, self::STATUS_IN_PROGRESS]);
    }

    /**
     * Get the purpose label.
     */
    public function getPurposeLabelAttribute(): string
    {
        return self::getPurposeLabels()[$this->purpose] ?? $this->purpose;
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatusLabels()[$this->status] ?? $this->status;
    }
}
