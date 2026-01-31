<?php

namespace App\Models;

use App\Enums\TicketFrequency;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'project_id',
        'frequency',
        'day_value',
        'lead_time_days',
        'initial_status_id',
        'priority_id',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'frequency' => TicketFrequency::class,
        'is_active' => 'boolean',
        'day_value' => 'integer',
        'lead_time_days' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function initialStatus(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class, 'initial_status_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'priority_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_template_assignees')
            ->withTimestamps();
    }

    public function generations(): HasMany
    {
        return $this->hasMany(TicketTemplateGeneration::class);
    }

    /**
     * Calculate the next target date based on frequency and day_value.
     */
    public function getNextTargetDate(?Carbon $fromDate = null): Carbon
    {
        $fromDate = $fromDate ?? Carbon::today();

        return match ($this->frequency) {
            TicketFrequency::Weekly => $this->calculateWeeklyTargetDate($fromDate),
            TicketFrequency::Monthly => $this->calculateMonthlyTargetDate($fromDate),
        };
    }

    /**
     * Calculate the next generation date (target_date - lead_time_days).
     */
    public function getNextGenerationDate(?Carbon $fromDate = null): Carbon
    {
        $targetDate = $this->getNextTargetDate($fromDate);

        return $targetDate->copy()->subDays($this->lead_time_days);
    }

    /**
     * Check if a ticket should be generated today.
     */
    public function shouldGenerateToday(?Carbon $today = null): bool
    {
        $today = $today ?? Carbon::today();
        $targetDate = $this->getNextTargetDate($today);
        $generationDate = $targetDate->copy()->subDays($this->lead_time_days);

        return $today->isSameDay($generationDate);
    }

    /**
     * Check if a ticket has already been generated for the given target date.
     */
    public function hasGeneratedForPeriod(Carbon $targetDate): bool
    {
        return $this->generations()
            ->whereDate('target_date', $targetDate->toDateString())
            ->exists();
    }

    /**
     * Calculate the next weekly target date.
     * day_value: 1=Monday, 7=Sunday (ISO-8601)
     */
    protected function calculateWeeklyTargetDate(Carbon $fromDate): Carbon
    {
        $targetDayOfWeek = $this->day_value;
        $currentDayOfWeek = $fromDate->dayOfWeekIso;

        if ($currentDayOfWeek === $targetDayOfWeek) {
            return $fromDate->copy();
        }

        if ($currentDayOfWeek < $targetDayOfWeek) {
            return $fromDate->copy()->addDays($targetDayOfWeek - $currentDayOfWeek);
        }

        // Target day has passed this week, get next week's occurrence
        return $fromDate->copy()->addDays(7 - $currentDayOfWeek + $targetDayOfWeek);
    }

    /**
     * Calculate the next monthly target date.
     * Handles months with fewer days (e.g., day 31 in February).
     */
    protected function calculateMonthlyTargetDate(Carbon $fromDate): Carbon
    {
        $targetDay = $this->day_value;
        $currentDay = $fromDate->day;
        $currentMonth = $fromDate->copy();

        // Try current month first
        $daysInCurrentMonth = $currentMonth->daysInMonth;
        $effectiveDay = min($targetDay, $daysInCurrentMonth);

        if ($currentDay <= $effectiveDay) {
            return $currentMonth->copy()->day($effectiveDay);
        }

        // Target day has passed, move to next month
        $nextMonth = $currentMonth->copy()->addMonth()->startOfMonth();
        $daysInNextMonth = $nextMonth->daysInMonth;
        $effectiveDay = min($targetDay, $daysInNextMonth);

        return $nextMonth->day($effectiveDay);
    }

    /**
     * Scope: Get only active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
