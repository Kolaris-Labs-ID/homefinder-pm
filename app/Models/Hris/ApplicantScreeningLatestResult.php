<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The only mutable "latest" marker — one row per (applicant, job). Result rows
 * stay immutable; only this pointer moves when a newer result is finalized.
 */
class ApplicantScreeningLatestResult extends Model
{
    protected $table = 'applicant_screening_latest_results';

    protected $guarded = ['id'];

    const CREATED_AT = null; // table carries only updated_at

    protected $casts = [
        'latest_version' => 'integer',
    ];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function latestResult(): BelongsTo
    {
        return $this->belongsTo(ApplicantScreeningResult::class, 'latest_result_id');
    }
}
