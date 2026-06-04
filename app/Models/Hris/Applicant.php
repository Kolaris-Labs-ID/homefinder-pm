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

    const STATUS_SCREENING = 'screening';

    const STATUS_SHORTLISTED = 'shortlisted';

    const STATUS_INTERVIEW_SCHEDULED = 'interview_scheduled';

    const STATUS_INTERVIEWED = 'interviewed';

    const STATUS_OFFERED = 'offered';

    const STATUS_HIRED = 'hired';

    const STATUS_REJECTED = 'rejected';

    const STATUS_WITHDRAWN = 'withdrawn';

    // Terminal statuses: an applicant here cannot advance, be rejected, or withdrawn.
    const TERMINAL_STATUSES = [self::STATUS_HIRED, self::STATUS_REJECTED, self::STATUS_WITHDRAWN];

    // Application source constants
    const SOURCE_MANUAL_HR = 'manual_hr';

    const SOURCE_JOB_PORTAL = 'job_portal';

    protected $fillable = [
        'job_posting_id',
        'name',
        'email',
        'normalized_email',
        'phone',
        'resume_file_path',
        'cover_letter',
        'source',
        'public_application_token',
        'application_reference_code',
        'consent_privacy_policy',
        'consent_data_processing',
        'consent_policy_version',
        'consent_accepted_at',
        'applied_ip',
        'applied_user_agent',
        'retention_until',
        'deletion_requested_at',
        'anonymized_at',
        'linkedin_url',
        'portfolio_url',
        'expected_salary',
        'available_start_date',
        'status',
        'rejection_reason',
        'hired_employee_id',
    ];

    protected $casts = [
        'consent_privacy_policy' => 'boolean',
        'consent_data_processing' => 'boolean',
        'consent_accepted_at' => 'datetime',
        'retention_until' => 'datetime',
        'deletion_requested_at' => 'datetime',
        'anonymized_at' => 'datetime',
        'available_start_date' => 'date',
        'expected_salary' => 'decimal:2',
    ];

    protected $attributes = [
        'source' => self::SOURCE_MANUAL_HR,
        'consent_privacy_policy' => false,
        'consent_data_processing' => false,
    ];

    protected static function booted(): void
    {
        static::saving(function (Applicant $applicant) {
            $applicant->normalized_email = static::normalizeEmail($applicant->email);
        });
    }

    /**
     * Normalize an email for duplicate detection: lowercase + trim only.
     * Plus-addressing and Gmail dots are intentionally preserved.
     */
    public static function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        return strtolower(trim($email));
    }

    /**
     * Get all available statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_APPLIED,
            self::STATUS_SCREENING,
            self::STATUS_SHORTLISTED,
            self::STATUS_INTERVIEW_SCHEDULED,
            self::STATUS_INTERVIEWED,
            self::STATUS_OFFERED,
            self::STATUS_HIRED,
            self::STATUS_REJECTED,
            self::STATUS_WITHDRAWN,
        ];
    }

    /**
     * Single source of truth for HR-driven forward transitions.
     * `hired` is intentionally absent: it is reachable only via
     * RecruitmentService::hireApplicant(), which links an Employee.
     */
    public static function forwardTransitions(): array
    {
        return [
            self::STATUS_APPLIED => self::STATUS_SCREENING,
            self::STATUS_SCREENING => self::STATUS_SHORTLISTED,
            self::STATUS_SHORTLISTED => self::STATUS_INTERVIEW_SCHEDULED,
            self::STATUS_INTERVIEW_SCHEDULED => self::STATUS_INTERVIEWED,
            self::STATUS_INTERVIEWED => self::STATUS_OFFERED,
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
     * Scope to filter active applicants (not in a terminal status).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::TERMINAL_STATUSES);
    }

    /**
     * Scope to filter withdrawn applicants.
     */
    public function scopeWithdrawn(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_WITHDRAWN);
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
     * Check if the applicant is in the screening stage.
     */
    public function isScreening(): bool
    {
        return $this->status === self::STATUS_SCREENING;
    }

    /**
     * Check if the applicant has an interview scheduled.
     */
    public function isInterviewScheduled(): bool
    {
        return $this->status === self::STATUS_INTERVIEW_SCHEDULED;
    }

    /**
     * Check if the applicant has withdrawn.
     */
    public function isWithdrawn(): bool
    {
        return $this->status === self::STATUS_WITHDRAWN;
    }

    /**
     * Check if the applicant is active (not in a terminal status).
     */
    public function isActive(): bool
    {
        return ! in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    /**
     * Check if the applicant can be advanced to the next pipeline status.
     */
    public function canAdvance(): bool
    {
        return $this->getNextStatus() !== null;
    }

    /**
     * Get the next forward status, or null if none (offered/terminal).
     */
    public function getNextStatus(): ?string
    {
        return self::forwardTransitions()[$this->status] ?? null;
    }
}
