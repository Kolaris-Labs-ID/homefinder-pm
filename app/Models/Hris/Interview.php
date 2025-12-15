<?php

namespace App\Models\Hris;

use App\Models\User;
use Database\Factories\Hris\InterviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Interview extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): InterviewFactory
    {
        return InterviewFactory::new();
    }

    // Result constants
    const RESULT_PENDING = 'pending';
    const RESULT_PASS = 'pass';
    const RESULT_FAIL = 'fail';

    // Interview type constants
    const TYPE_PHONE = 'phone';
    const TYPE_VIDEO = 'video';
    const TYPE_ONSITE = 'onsite';

    protected $fillable = [
        'applicant_id',
        'interviewer_id',
        'scheduled_at',
        'location',
        'interview_type',
        'result',
        'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    /**
     * Get all available results.
     */
    public static function getResults(): array
    {
        return [
            self::RESULT_PENDING,
            self::RESULT_PASS,
            self::RESULT_FAIL,
        ];
    }

    /**
     * Get all available interview types.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_PHONE,
            self::TYPE_VIDEO,
            self::TYPE_ONSITE,
        ];
    }

    /**
     * Get the applicant that owns the interview.
     */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    /**
     * Get the interviewer (user) that conducts the interview.
     */
    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }

    /**
     * Scope to filter by applicant.
     */
    public function scopeForApplicant(Builder $query, int $applicantId): Builder
    {
        return $query->where('applicant_id', $applicantId);
    }

    /**
     * Scope to filter by interviewer.
     */
    public function scopeForInterviewer(Builder $query, int $interviewerId): Builder
    {
        return $query->where('interviewer_id', $interviewerId);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('scheduled_at', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by result.
     */
    public function scopeResult(Builder $query, string $result): Builder
    {
        return $query->where('result', $result);
    }

    /**
     * Scope to filter pending interviews.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('result', self::RESULT_PENDING);
    }

    /**
     * Scope to filter passed interviews.
     */
    public function scopePassed(Builder $query): Builder
    {
        return $query->where('result', self::RESULT_PASS);
    }

    /**
     * Scope to filter failed interviews.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('result', self::RESULT_FAIL);
    }

    /**
     * Scope to filter by interview type.
     */
    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('interview_type', $type);
    }

    /**
     * Check if the interview is pending.
     */
    public function isPending(): bool
    {
        return $this->result === self::RESULT_PENDING;
    }

    /**
     * Check if the interview passed.
     */
    public function isPassed(): bool
    {
        return $this->result === self::RESULT_PASS;
    }

    /**
     * Check if the interview failed.
     */
    public function isFailed(): bool
    {
        return $this->result === self::RESULT_FAIL;
    }

    /**
     * Check if the interview is completed.
     */
    public function isCompleted(): bool
    {
        return !$this->isPending();
    }

    /**
     * Check if the interview is a phone interview.
     */
    public function isPhone(): bool
    {
        return $this->interview_type === self::TYPE_PHONE;
    }

    /**
     * Check if the interview is a video interview.
     */
    public function isVideo(): bool
    {
        return $this->interview_type === self::TYPE_VIDEO;
    }

    /**
     * Check if the interview is an onsite interview.
     */
    public function isOnsite(): bool
    {
        return $this->interview_type === self::TYPE_ONSITE;
    }
}
