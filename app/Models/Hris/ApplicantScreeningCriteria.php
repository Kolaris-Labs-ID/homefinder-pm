<?php

namespace App\Models\Hris;

use App\Models\User;
use App\Services\Hris\Screening\SensitiveCriteriaGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class ApplicantScreeningCriteria extends Model
{
    use SoftDeletes;

    protected $table = 'applicant_screening_criteria';

    protected $guarded = ['id'];

    protected $casts = [
        'keywords' => 'array',
        'synonyms' => 'array',
        'weight' => 'integer',
        'sort_order' => 'integer',
        'missing_means_failed' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'weight' => 1,
        'is_active' => true,
        'sort_order' => 0,
        'missing_means_failed' => false,
    ];

    const GROUP_MUST_HAVE = 'must_have';

    const GROUP_NICE_TO_HAVE = 'nice_to_have';

    const GROUP_FIT = 'fit';

    const GROUP_KNOCKOUT = 'knockout';

    public static function groups(): array
    {
        return [self::GROUP_MUST_HAVE, self::GROUP_NICE_TO_HAVE, self::GROUP_FIT, self::GROUP_KNOCKOUT];
    }

    public static function normalizeLabel(?string $label): string
    {
        return trim(mb_strtolower((string) $label));
    }

    protected static function booted(): void
    {
        static::saving(function (self $criteria) {
            if ((int) $criteria->weight < 1) {
                throw new InvalidArgumentException('Screening criteria weight must be >= 1.');
            }

            app(SensitiveCriteriaGuard::class)->assertClean(
                $criteria->label,
                $criteria->keywords,
                $criteria->synonyms,
            );

            // Keep the NULL-distinct uniqueness key in sync while the row is alive.
            if ($criteria->deleted_at === null) {
                $criteria->label_active_key = self::normalizeLabel($criteria->label);
            }
        });

        // Soft delete is a direct UPDATE that bypasses saving/updating events, so clear
        // the key here to free the (job, group, label) slot for re-creation.
        static::deleted(function (self $criteria) {
            if ($criteria->isForceDeleting()) {
                return;
            }
            static::withTrashed()->whereKey($criteria->getKey())->update(['label_active_key' => null]);
        });

        static::restored(function (self $criteria) {
            static::withTrashed()->whereKey($criteria->getKey())
                ->update(['label_active_key' => self::normalizeLabel($criteria->label)]);
        });
    }

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
