<?php

namespace App\Models\Hris;

use Database\Factories\Hris\HiringCompanyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class HiringCompany extends Model
{
    use HasFactory;

    const STATUS_ACTIVE = 'active';

    const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name',
        'slug',
        'logo_path',
        'website',
        'industry',
        'location',
        'description',
        'is_internal',
        'status',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
        'is_internal' => false,
    ];

    protected static function newFactory(): HiringCompanyFactory
    {
        return HiringCompanyFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (HiringCompany $company) {
            if (blank($company->slug)) {
                $company->slug = $company->generateUniqueSlug($company->name);
            }
        });

        static::updating(function (HiringCompany $company) {
            if ($company->isDirty('slug') && $company->hasPublishedPublicJob()) {
                throw new \RuntimeException('The company slug is locked once a public job has been published.');
            }
        });
    }

    /**
     * Build a unique kebab-case slug, suffixing with a counter on collision.
     */
    public function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function jobPostings(): HasMany
    {
        return $this->hasMany(JobPosting::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Whether this company already has a published public job — once true,
     * the slug must not change (so public/SEO URLs stay stable).
     */
    public function hasPublishedPublicJob(): bool
    {
        return $this->jobPostings()
            ->where('is_public', true)
            ->whereNotNull('published_public_at')
            ->exists();
    }
}
