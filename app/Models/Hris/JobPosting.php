<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class JobPosting extends Model
{
    use HasFactory;

    // Status constants
    const STATUS_DRAFT = 'draft';

    const STATUS_PUBLISHED = 'published';

    const STATUS_CLOSED = 'closed';

    // Employment type constants
    const EMPLOYMENT_TYPE_FULL_TIME = 'full_time';

    const EMPLOYMENT_TYPE_PART_TIME = 'part_time';

    const EMPLOYMENT_TYPE_CONTRACT = 'contract';

    const EMPLOYMENT_TYPE_INTERN = 'intern';

    // Work arrangement constants (public portal)
    const WORK_ARRANGEMENT_ONSITE = 'onsite';

    const WORK_ARRANGEMENT_HYBRID = 'hybrid';

    const WORK_ARRANGEMENT_REMOTE = 'remote';

    protected $fillable = [
        'title',
        'slug',
        'department_id',
        'hiring_company_id',
        'location',
        'work_arrangement',
        'employment_type',
        'description',
        'requirements',
        'public_title',
        'public_description',
        'public_requirements',
        'public_benefits',
        'salary_min',
        'salary_max',
        'salary_hidden',
        'application_deadline',
        'is_public',
        'published_public_at',
        'closed_public_at',
        'status',
        'published_at',
        'closed_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'closed_at' => 'datetime',
        'published_public_at' => 'datetime',
        'closed_public_at' => 'datetime',
        'application_deadline' => 'date',
        'is_public' => 'boolean',
        'salary_hidden' => 'boolean',
        'salary_min' => 'decimal:2',
        'salary_max' => 'decimal:2',
    ];

    protected $attributes = [
        'salary_hidden' => true,
        'is_public' => false,
    ];

    protected static function booted(): void
    {
        static::creating(function (JobPosting $posting) {
            if (blank($posting->slug)) {
                $posting->slug = $posting->generateUniqueSlug($posting->title);
            }
        });
    }

    /**
     * Build a unique kebab-case slug, suffixing with a counter on collision.
     */
    public function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /**
     * Get all available statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_PUBLISHED,
            self::STATUS_CLOSED,
        ];
    }

    /**
     * Get all available employment types.
     */
    public static function getEmploymentTypes(): array
    {
        return [
            self::EMPLOYMENT_TYPE_FULL_TIME,
            self::EMPLOYMENT_TYPE_PART_TIME,
            self::EMPLOYMENT_TYPE_CONTRACT,
            self::EMPLOYMENT_TYPE_INTERN,
        ];
    }

    /**
     * Get the department that owns the job posting.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the hiring company that owns the job posting.
     */
    public function hiringCompany(): BelongsTo
    {
        return $this->belongsTo(HiringCompany::class);
    }

    /**
     * Scope: jobs that may be shown on the public portal.
     * Published + flagged public + active company + deadline open (Asia/Jakarta).
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        $today = now('Asia/Jakarta')->toDateString();

        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->where('is_public', true)
            ->whereHas('hiringCompany', fn (Builder $q) => $q->where('status', HiringCompany::STATUS_ACTIVE))
            ->where(function (Builder $q) use ($today) {
                $q->whereNull('application_deadline')
                    ->orWhereDate('application_deadline', '>=', $today);
            });
    }

    /**
     * Salary payload for public serialization: empty when hidden so the
     * keys are omitted entirely (never emitted as null).
     */
    public function publicSalary(): array
    {
        if ($this->salary_hidden) {
            return [];
        }

        return [
            'salary_min' => $this->salary_min === null ? null : (int) $this->salary_min,
            'salary_max' => $this->salary_max === null ? null : (int) $this->salary_max,
        ];
    }

    /**
     * Get all available work arrangements.
     */
    public static function getWorkArrangements(): array
    {
        return [
            self::WORK_ARRANGEMENT_ONSITE,
            self::WORK_ARRANGEMENT_HYBRID,
            self::WORK_ARRANGEMENT_REMOTE,
        ];
    }

    /**
     * Get the applicants for the job posting.
     */
    public function applicants(): HasMany
    {
        return $this->hasMany(Applicant::class);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter draft job postings.
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Scope to filter published job postings.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * Scope to filter closed job postings.
     */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    /**
     * Scope to filter by department.
     */
    public function scopeForDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Check if the job posting is draft.
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if the job posting is published.
     */
    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Check if the job posting is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * Get the applicant count attribute.
     */
    public function getApplicantCountAttribute(): int
    {
        return $this->applicants()->count();
    }
}
