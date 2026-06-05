<?php

namespace App\Models\Hris;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * HR override on the (applicant, job) pair — survives re-screening because it is
 * joined onto the latest result, not stored on any result row. Exactly one active
 * override per pair (enforced via the NULL-distinct active_marker).
 */
class ApplicantScreeningOverride extends Model
{
    protected $table = 'applicant_screening_overrides';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'cleared_at' => 'datetime',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        static::saving(function (self $override) {
            $override->active_marker = $override->is_active ? 1 : null;
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function clearedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
