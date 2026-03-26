<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_type',
        'subject_id',
        'project_id',
        'user_id',
        'event_type',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function recordForSubject(
        EloquentModel $subject,
        string $eventType,
        ?User $user = null,
        array $meta = [],
    ): self {
        $projectId = match (true) {
            $subject instanceof Project => $subject->id,
            $subject instanceof Ticket => $subject->project_id,
            default => null,
        };

        return static::create([
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'project_id' => $projectId,
            'user_id' => $user?->id,
            'event_type' => $eventType,
            'meta' => $meta,
        ]);
    }

    public static function logProjectCreated(Project $project, ?User $user = null): self
    {
        return static::recordForSubject($project, 'project_created', $user, [
            'name' => $project->name,
            'ticket_prefix' => $project->ticket_prefix,
        ]);
    }

    public static function logTicketCreated(Ticket $ticket, ?User $user = null): self
    {
        return static::recordForSubject($ticket, 'ticket_created', $user, [
            'name' => $ticket->name,
            'uuid' => $ticket->uuid,
        ]);
    }

    public static function logTicketStatusChanged(
        Ticket $ticket,
        ?User $user = null,
        ?int $fromStatusId = null,
        ?string $fromStatusName = null,
        ?int $toStatusId = null,
        ?string $toStatusName = null,
    ): self {
        return static::recordForSubject($ticket, 'ticket_status_changed', $user, [
            'name' => $ticket->name,
            'uuid' => $ticket->uuid,
            'from_status_id' => $fromStatusId,
            'from_status_name' => $fromStatusName,
            'to_status_id' => $toStatusId,
            'to_status_name' => $toStatusName,
        ]);
    }

    public static function logTicketUpdated(
        Ticket $ticket,
        ?User $user = null,
        array $changedFields = [],
        array $meta = [],
    ): ?self {
        $changedFields = array_values(array_unique(array_filter($changedFields)));

        if ($changedFields === []) {
            return null;
        }

        return static::recordForSubject($ticket, 'ticket_updated', $user, array_merge([
            'name' => $ticket->name,
            'uuid' => $ticket->uuid,
            'changed_fields' => $changedFields,
        ], $meta));
    }
}
