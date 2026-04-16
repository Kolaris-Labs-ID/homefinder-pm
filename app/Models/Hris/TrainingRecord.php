<?php

namespace App\Models\Hris;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrainingRecord extends Model
{
    use HasFactory;

    // Status constants
    const STATUS_PLANNED = 'planned';

    const STATUS_IN_PROGRESS = 'in_progress';

    const STATUS_COMPLETED = 'completed';

    // Training type constants
    const TRAINING_TYPE_FREE = 'free';

    const TRAINING_TYPE_PAID = 'paid';

    // PGP point constants
    const PGP_FREE_TRAINING = 35;

    const PGP_PAID_MIN = 15;

    const PGP_PAID_MAX = 25;

    protected $fillable = [
        'employee_id',
        'training_name',
        'provider',
        'description',
        'completion_date',
        'expiry_date',
        'certificate_file_path',
        'status',
        'training_type',
        'pgp_points',
        'approved_at',
    ];

    protected $casts = [
        'completion_date' => 'date',
        'expiry_date' => 'date',
        'approved_at' => 'datetime',
        'pgp_points' => 'integer',
    ];

    /**
     * Get all available statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PLANNED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
        ];
    }

    /**
     * Get the employee that owns this training record.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter planned trainings.
     */
    public function scopePlanned(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PLANNED);
    }

    /**
     * Scope to filter in-progress trainings.
     */
    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    /**
     * Scope to filter completed trainings.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope to filter certifications expiring within a given number of days.
     */
    public function scopeExpiringWithinDays(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', now())
            ->where('expiry_date', '<=', now()->addDays($days));
    }

    /**
     * Scope to filter expired certifications.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now());
    }

    /**
     * Scope to filter by employee.
     */
    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Check if the training is planned.
     */
    public function isPlanned(): bool
    {
        return $this->status === self::STATUS_PLANNED;
    }

    /**
     * Check if the training is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Check if the training is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the certification is expiring within a given number of days.
     */
    public function isExpiringWithinDays(int $days = 30): bool
    {
        if (! $this->expiry_date) {
            return false;
        }

        return $this->expiry_date->isBetween(now(), now()->addDays($days));
    }

    /**
     * Check if the certification has expired.
     */
    public function isExpired(): bool
    {
        if (! $this->expiry_date) {
            return false;
        }

        return $this->expiry_date->isPast();
    }

    /**
     * Get the days until expiry.
     */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (! $this->expiry_date) {
            return null;
        }

        return (int) now()->diffInDays($this->expiry_date, false);
    }

    /**
     * Get all available training types.
     */
    public static function getTrainingTypes(): array
    {
        return [
            self::TRAINING_TYPE_FREE,
            self::TRAINING_TYPE_PAID,
        ];
    }

    /**
     * Check if this is a paid training.
     */
    public function isPaidTraining(): bool
    {
        return $this->training_type === self::TRAINING_TYPE_PAID;
    }

    /**
     * Check if this is a free training.
     */
    public function isFreeTraining(): bool
    {
        return $this->training_type === self::TRAINING_TYPE_FREE;
    }

    /**
     * Check if this training has been approved for PGP.
     */
    public function isApprovedForPgp(): bool
    {
        return $this->approved_at !== null
            && $this->pgp_points !== null
            && $this->pgp_points > 0;
    }

    /**
     * Called when the approval workflow completes successfully.
     * Training completion status stays in planned / in_progress / completed — PGP is stored in approved_at / pgp_points.
     */
    public function markAsApproved(): void
    {
        // Intentionally empty: do not set status to "approved" (invalid for training_records enum).
    }

    /**
     * Called when the approval workflow rejects the request.
     * Do not set status to "rejected" (invalid for training_records enum); rejection is on approval_steps.
     */
    public function markAsRejected(string $reason): void
    {
        // Intentionally empty: completion status remains planned / in_progress / completed.
    }

    /**
     * Scope to filter training records approved within a specific quarter.
     */
    public function scopeApprovedInQuarter(Builder $query, int $quarter, int $year): Builder
    {
        $startMonth = ($quarter - 1) * 3 + 1;
        $start = Carbon::create($year, $startMonth, 1)->startOfDay();
        $end = $start->copy()->addMonths(3)->subSecond();

        return $query->whereNotNull('approved_at')
            ->where('approved_at', '>=', $start)
            ->where('approved_at', '<=', $end);
    }

    /**
     * Scope to filter training records that have PGP points assigned.
     */
    public function scopeWithPgpPoints(Builder $query): Builder
    {
        return $query->whereNotNull('pgp_points')
            ->where('pgp_points', '>', 0);
    }
}
