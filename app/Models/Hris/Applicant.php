<?php

namespace App\Models\Hris;

use Database\Factories\Hris\ApplicantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Applicant extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ApplicantFactory
    {
        return ApplicantFactory::new();
    }

    // Status constants for pipeline stages
    const STATUS_APPLIED = 'applied';
    const STATUS_SHORTLISTED = 'shortlisted';
    const STATUS_INTERVIEWED = 'interviewed';
    const STATUS_OFFERED = 'offered';
    const STATUS_HIRED = 'hired';
    const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'job_posting_id',
        'name',
        'email',
        'phone',
        'resume_file_path',
        'cover_letter',
        'status',
        'rejection_reason',
        'hired_employee_id',
    ];

    /**
     * Get all available statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_APPLIED,
            self::STATUS_SHORTLISTED,
            self::STATUS_INTERVIEWED,
            self::STATUS_OFFERED,
            self::STATUS_HIRED,
            self::STATUS_REJECTED,
        ];
    }

    /**
     * Get the valid pipeline statuses (excluding rejected).
     */
    public static function getPipelineStatuses(): array
    {
        return [
            self::STATUS_APPLIED,
            self::STATUS_SHORTLISTED,
            self::STATUS_INTERVIEWED,
            self::STATUS_OFFERED,
            self::STATUS_HIRED,
        ];
    }

    /**
     * Get the job posting that owns the applicant.
     */
    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    /**
     * Get the interviews for the applicant.
     */
    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }

    /**
     * Get the employee record if hired.
     */
    public function hiredEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'hired_employee_id');
    }

    /**
     * Scope to filter by job posting.
     */
    public function scopeForJobPosting(Builder $query, int $jobPostingId): Builder
    {
        return $query->where('job_posting_id', $jobPostingId);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter applied applicants.
     */
    public function scopeApplied(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPLIED);
    }

    /**
     * Scope to filter shortlisted applicants.
     */
    public function scopeShortlisted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SHORTLISTED);
    }

    /**
     * Scope to filter interviewed applicants.
     */
    public function scopeInterviewed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_INTERVIEWED);
    }

    /**
     * Scope to filter offered applicants.
     */
    public function scopeOffered(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OFFERED);
    }

    /**
     * Scope to filter hired applicants.
     */
    public function scopeHired(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_HIRED);
    }

    /**
     * Scope to filter rejected applicants.
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Scope to filter active applicants (not rejected or hired).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [self::STATUS_REJECTED, self::STATUS_HIRED]);
    }

    /**
     * Check if the applicant is applied.
     */
    public function isApplied(): bool
    {
        return $this->status === self::STATUS_APPLIED;
    }

    /**
     * Check if the applicant is shortlisted.
     */
    public function isShortlisted(): bool
    {
        return $this->status === self::STATUS_SHORTLISTED;
    }

    /**
     * Check if the applicant is interviewed.
     */
    public function isInterviewed(): bool
    {
        return $this->status === self::STATUS_INTERVIEWED;
    }

    /**
     * Check if the applicant is offered.
     */
    public function isOffered(): bool
    {
        return $this->status === self::STATUS_OFFERED;
    }

    /**
     * Check if the applicant is hired.
     */
    public function isHired(): bool
    {
        return $this->status === self::STATUS_HIRED;
    }

    /**
     * Check if the applicant is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Check if the applicant can be advanced to the next status.
     */
    public function canAdvance(): bool
    {
        return !$this->isHired() && !$this->isRejected();
    }

    /**
     * Get the next status in the pipeline.
     */
    public function getNextStatus(): ?string
    {
        $pipeline = self::getPipelineStatuses();
        $currentIndex = array_search($this->status, $pipeline);

        if ($currentIndex === false || $currentIndex >= count($pipeline) - 1) {
            return null;
        }

        return $pipeline[$currentIndex + 1];
    }
}
