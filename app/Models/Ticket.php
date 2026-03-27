<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'ticket_status_id',
        'priority_id',
        'name',
        'description',
        'start_date',
        'due_date',
        'uuid',
        'epic_id',
        'created_by',
        'archived_at',
        'archived_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'archived_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($ticket) {
            if (empty($ticket->uuid)) {
                $project = Project::find($ticket->project_id);
                $prefix = $project ? $project->ticket_prefix : 'TKT';
                $randomString = Str::upper(Str::random(6));

                $ticket->uuid = "{$prefix}-{$randomString}";
            }

            // Set created_by jika belum di-set dan ada user yang login
            if (empty($ticket->created_by) && Auth::id()) {
                $ticket->created_by = Auth::id();
            }
        });

        static::created(function ($ticket) {
            ActivityLog::logTicketCreated($ticket, Auth::user());
        });

        static::updating(function ($ticket) {
            if ($ticket->isDirty('ticket_status_id')) {
                $fromStatusId = $ticket->getOriginal('ticket_status_id');
                $toStatusId = $ticket->ticket_status_id;

                TicketHistory::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => Auth::id(),
                    'ticket_status_id' => $toStatusId,
                ]);

                ActivityLog::logTicketStatusChanged(
                    $ticket,
                    Auth::user(),
                    $fromStatusId,
                    TicketStatus::find($fromStatusId)?->name,
                    $toStatusId,
                    TicketStatus::find($toStatusId)?->name,
                );
            }

            $changedFields = collect(array_keys($ticket->getDirty()))
                ->reject(fn (string $field): bool => in_array($field, [
                    'updated_at',
                    'ticket_status_id',
                ], true))
                ->values()
                ->all();

            ActivityLog::logTicketUpdated($ticket, Auth::user(), $changedFields);
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class, 'ticket_status_id');
    }

    // Multi-user assignment relationship
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_users');
    }

    // Creator relationship
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TicketHistory::class)->orderBy('created_at', 'desc');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class)->orderBy('created_at', 'asc');
    }

    public function epic(): BelongsTo
    {
        return $this->belongsTo(Epic::class);
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'priority_id');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function getIsArchivedAttribute(): bool
    {
        return !is_null($this->archived_at);
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNotNull('archived_at');
    }

    // Helper methods
    public function assignUser(User $user): void
    {
        $this->assignees()->syncWithoutDetaching($user->id);
    }

    public function unassignUser(User $user): void
    {
        $this->assignees()->detach($user->id);
    }

    public function assignUsers(array $userIds): void
    {
        $this->assignees()->sync($userIds);
    }

    public function syncAssigneesWithActivity(array $userIds, ?User $actor = null): void
    {
        $before = $this->assignees()
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $after = collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->assignees()->sync($after);

        if ($before === $after) {
            return;
        }

        ActivityLog::logTicketUpdated($this, $actor, ['assignees'], [
            'added_assignee_ids' => array_values(array_diff($after, $before)),
            'removed_assignee_ids' => array_values(array_diff($before, $after)),
        ]);
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assignees()->where('user_id', $user->id)->exists();
    }

    /**
     * Check if ticket is completed
     */
    public function isCompleted(): bool
    {
        $statusName = strtolower($this->status?->name ?? '');
        return ($this->status && $this->status->is_completed) || 
               in_array($statusName, ['done', 'completed', 'closed']);
    }

    /**
     * Check if ticket is overdue
     */
    public function isOverdue(): bool
    {
        if (!$this->due_date) {
            return false;
        }

        // Consider ticket overdue only if it's not completed
        if ($this->isCompleted()) {
            return false;
        }

        return Carbon::parse($this->due_date)->isPast();
    }

    /**
     * Get days until due date (negative if overdue)
     */
    public function getDaysUntilDue(): ?int
    {
        if (!$this->due_date) {
            return null;
        }

        $today = Carbon::today();
        $dueDate = Carbon::parse($this->due_date);

        return $today->diffInDays($dueDate, false);
    }

    /**
     * Check if ticket is nearing overdue (within specified days)
     */
    public function isNearingOverdue(int $days = 3): bool
    {
        if (!$this->due_date) {
            return false;
        }

        // Don't notify if already overdue or completed
        if ($this->isOverdue()) {
            return false;
        }

        $isCompleted = $this->status && $this->status->is_completed;
        if ($isCompleted) {
            return false;
        }

        $daysUntilDue = $this->getDaysUntilDue();
        return $daysUntilDue !== null && $daysUntilDue >= 0 && $daysUntilDue <= $days;
    }

    /**
     * Scope: Get overdue tickets
     */
    public function scopeOverdue($query)
    {
        return $query->whereNotNull('due_date')
            ->whereDate('due_date', '<', Carbon::today())
            ->whereHas('status', function ($q) {
                $q->where('is_completed', false);
            });
    }

    /**
     * Scope: Get tickets nearing overdue
     */
    public function scopeNearingOverdue($query, int $days = 3)
    {
        $today = Carbon::today();
        $warningDate = $today->copy()->addDays($days);

        return $query->whereNotNull('due_date')
            ->whereDate('due_date', '>=', $today)
            ->whereDate('due_date', '<=', $warningDate)
            ->whereHas('status', function ($q) {
                $q->where('is_completed', false);
            });
    }
}