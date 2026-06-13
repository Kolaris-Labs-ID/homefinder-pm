<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'ticket_prefix',
        'start_date',
        'end_date',
        'pinned_date',
        'archived_at',
        'archived_by',
        'done_at',
        'is_auto_done',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'pinned_date' => 'datetime',
        'archived_at' => 'datetime',
        'done_at' => 'datetime',
        'is_auto_done' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($project) {
            if (empty($project->created_by) && Auth::id()) {
                $project->created_by = Auth::id();
            }
        });

        static::created(function ($project) {
            ActivityLog::logProjectCreated($project, Auth::user());
        });
    }

    // Helper method to check if project is pinned
    public function getIsPinnedAttribute(): bool
    {
        return ! is_null($this->pinned_date);
    }

    // Helper method to check if project is archived
    public function getIsArchivedAttribute(): bool
    {
        return ! is_null($this->archived_at);
    }

    // Helper method to check if project is done
    public function getIsDoneAttribute(): bool
    {
        return ! is_null($this->done_at);
    }

    // Relationship to the user who archived the project
    public function archivedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Helper method to pin project
    public function pin(): void
    {
        $this->update(['pinned_date' => now()]);
    }

    // Helper method to unpin project
    public function unpin(): void
    {
        $this->update(['pinned_date' => null]);
    }

    // Scope to filter active (non-archived) projects
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNull('archived_at');
    }

    // Scope to filter archived projects
    public function scopeArchived(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNotNull('archived_at');
    }

    // Check if all tickets in the project are completed
    public function allTicketsCompleted(): bool
    {
        $totalTickets = $this->tickets()->count();

        if ($totalTickets === 0) {
            return false; // Empty projects don't auto-complete
        }

        $incompleteTickets = $this->tickets()
            ->whereHas('status', function ($query) {
                $query->where('is_completed', false);
            })
            ->count();

        return $incompleteTickets === 0;
    }

    // Check if project has active epics (epics with incomplete tickets)
    public function hasActiveEpics(): bool
    {
        return $this->epics()
            ->whereHas('tickets', function ($query) {
                $query->whereHas('status', function ($statusQuery) {
                    $statusQuery->where('is_completed', false);
                });
            })
            ->exists();
    }

    public function ticketStatuses(): HasMany
    {
        return $this->hasMany(TicketStatus::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withTimestamps();
    }

    // Add this method for Filament RelationManager compatibility
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withTimestamps();
    }

    public function epics(): HasMany
    {
        return $this->hasMany(Epic::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ProjectNote::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
    }

    public function getRemainingDaysAttribute()
    {
        if (! $this->end_date) {
            return null;
        }

        $today = Carbon::today();
        $endDate = Carbon::parse($this->end_date);

        if ($today->gt($endDate)) {
            return 0;
        }

        return (int) $today->diffInDays($endDate);
    }

    public function getProgressPercentageAttribute(): float
    {
        $totalTickets = $this->tickets()->count();

        if ($totalTickets === 0) {
            return 0.0;
        }

        $completedTickets = $this->tickets()
            ->whereHas('status', function ($query) {
                $query->where('is_completed', true);
            })
            ->count();

        return round(($completedTickets / $totalTickets) * 100, 1);
    }

    public function externalAccess(): HasOne
    {
        return $this->hasOne(ExternalAccess::class);
    }

    public function generateExternalAccess()
    {
        $this->externalAccess()?->delete();

        return ExternalAccess::generateForProject($this->id);
    }
}
