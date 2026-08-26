<?php

namespace App\Models\Hris;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeLog extends Model
{
    use HasFactory;

    // Status constants
    const STATUS_RUNNING  = 'running';
    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_FLAGGED  = 'flagged';

    // PP 35/2021 art. 26: overtime is capped at 4 hours per day and 18 hours per
    // week. Rest-day and public-holiday work is excluded from both ceilings and
    // is priced through the weekend/holiday attendance path instead.
    const MAX_DAILY_PAYABLE_MINUTES  = 240;
    const MAX_WEEKLY_PAYABLE_MINUTES = 1080;

    protected $fillable = [
        'user_id',
        'manager_id',
        'task_name',
        'description',
        'client_name',
        'start_time',
        'end_time',
        'duration_minutes',
        'hourly_rate',
        'evidence_path',
        'status',
        'rejection_note',
        'auto_closed',
        'overtime_payment_id',
    ];

    protected $casts = [
        'start_time'       => 'datetime',
        'end_time'         => 'datetime',
        'duration_minutes' => 'integer',
        'hourly_rate'      => 'decimal:2',
        'auto_closed'      => 'boolean',
        'overtime_payment_id' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function overtimePayment(): BelongsTo
    {
        return $this->belongsTo(OvertimePayment::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RUNNING);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereNull('overtime_payment_id');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    // -------------------------------------------------------------------------
    // Business Logic
    // -------------------------------------------------------------------------

    // Idempotent, so a narrower allocation from the pay run survives the clamp.
    public function payableMinutes(?int $minutes = null): int
    {
        $mins = (int) ($minutes ?? $this->duration_minutes);

        return max(0, min($mins, self::MAX_DAILY_PAYABLE_MINUTES));
    }

    public function isCapped(?int $minutes = null): bool
    {
        return (int) ($minutes ?? $this->duration_minutes) > self::MAX_DAILY_PAYABLE_MINUTES;
    }

    /**
     * Compute estimated overtime pay based on Indonesian Labor Law:
     *   - First 60 minutes (1 hour): 1.5 × hourly_rate
     *   - Each subsequent 60 minutes: 2.0 × hourly_rate
     *
     * Minutes beyond the statutory daily ceiling are not priced — see
     * payableMinutes().
     *
     * @param  int|null $minutes  Override duration; defaults to stored duration_minutes.
     * @return float
     */
    public function calculateEstimatedPay(?int $minutes = null): float
    {
        $mins = $this->payableMinutes($minutes);
        $rate = (float) $this->hourly_rate;

        if ($rate <= 0 || $mins <= 0) {
            return 0.0;
        }

        $totalHours = $mins / 60;

        if ($totalHours <= 1) {
            return $rate * 1.5 * $totalHours;
        }

        // First hour at 1.5x, subsequent hours at 2.0x
        $firstHourPay      = $rate * 1.5;
        $remainingHours    = $totalHours - 1;
        $remainingPay      = $rate * 2.0 * $remainingHours;

        return $firstHourPay + $remainingPay;
    }

    /**
     * Compute and persist duration_minutes from start_time to end_time (or now).
     */
    public function computeDuration(): int
    {
        $start = $this->start_time;
        $end   = $this->end_time ?? now();

        return (int) $start->diffInMinutes($end);
    }

    /**
     * Whether a client was attached to this session (evidence becomes required on stop).
     */
    public function hasClient(): bool
    {
        return ! empty($this->client_name);
    }

    /**
     * Status label helpers.
     */
    public static function getStatusLabels(): array
    {
        return [
            self::STATUS_RUNNING  => 'Running',
            self::STATUS_PENDING  => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_FLAGGED  => 'Flagged (Auto-Closed)',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::getStatusLabels()[$this->status] ?? $this->status;
    }
}
