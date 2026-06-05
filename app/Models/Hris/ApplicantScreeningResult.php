<?php

namespace App\Models\Hris;

use App\Exceptions\ImmutableScreeningResultException;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only, immutable screening result. One row per (applicant, job, version).
 * Never updated after creation — a re-run inserts a new version and the latest
 * pointer table moves. The `updating` guard is defence-in-depth, not the only seam.
 */
class ApplicantScreeningResult extends Model
{
    protected $table = 'applicant_screening_results';

    protected $guarded = ['id'];

    protected $casts = [
        'version' => 'integer',
        'score' => 'decimal:2',
        'flags' => 'array',
        'matched_criteria' => 'array',
        'missing_criteria' => 'array',
        'fit_outcomes' => 'array',
        'raw_scoring_breakdown' => 'array',
        'criteria_snapshot' => 'array',
        'redacted_fields' => 'array',
        'screening_reference_date' => 'date',
        'screened_at' => 'datetime',
    ];

    // parser_status
    const PARSER_NOT_STARTED = 'not_started';

    const PARSER_PENDING = 'pending';

    const PARSER_PARSED = 'parsed';

    const PARSER_FAILED = 'failed';

    // recommendation
    const REC_NOT_SCREENED = 'not_screened';

    const REC_NEEDS_MANUAL_REVIEW = 'needs_manual_review';

    const REC_LOW_MATCH = 'low_match';

    const REC_REVIEW = 'review';

    const REC_STRONG_MATCH = 'strong_match';

    protected static function booted(): void
    {
        static::updating(function (self $result) {
            throw new ImmutableScreeningResultException;
        });
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function hiringCompany(): BelongsTo
    {
        return $this->belongsTo(HiringCompany::class);
    }

    public function screenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'screened_by');
    }
}
