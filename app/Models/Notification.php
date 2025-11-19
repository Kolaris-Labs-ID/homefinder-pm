<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'type',
        'title', 
        'message',
        'data',
        'read_at'
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime'
    ];

    protected $appends = [
        'human_message',
        'relative_time',
        'action_buttons',
    ];

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeRead(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }

    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }

    public function isUnread(): bool
    {
        return is_null($this->read_at);
    }

    public function markAsRead(): bool
    {
        $this->read_at = now();
        return $this->save();
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'data->ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getTicketAttribute()
    {
        if (isset($this->data['ticket_id'])) {
            return Ticket::with('project')->find($this->data['ticket_id']);
        }
        return null;
    }

    public function getTicketNameAttribute()
    {
        $ticket = $this->getTicketAttribute();
        return $ticket ? $ticket->name : null;
    }

    public function getProjectNameAttribute()
    {
        $ticket = $this->getTicketAttribute();
        return $ticket && $ticket->project ? $ticket->project->name : null;
    }

    public function getActorAttribute(): ?array
    {
        $actorId = $this->data['actor_id'] ?? null;
        $actorName = $this->data['actor_name'] ?? null;

        if (! $actorId && ! $actorName) {
            return null;
        }

        $actor = null;
        if ($actorId) {
            $actor = User::find($actorId);
        }

        return [
            'id' => $actorId,
            'name' => $actorName ?? $actor?->name,
            'avatar' => $this->data['actor_avatar'] ?? $actor?->avatar_url,
            'verified' => (bool) ($this->data['actor_verified'] ?? false),
        ];
    }

    public function getHumanMessageAttribute(): string
    {
        $ticketName = $this->data['ticket_name'] ?? $this->ticket_name;
        $projectName = $this->data['project_name'] ?? $this->project_name;
        $actor = $this->actor['name'] ?? 'Someone';

        return match ($this->type) {
            'comment_added' => "{$actor} replied on {$ticketName}",
            'comment_updated' => "{$actor} updated a comment on {$ticketName}",
            'project_assigned' => "{$actor} added you to {$projectName}",
            'project_removed' => "{$actor} removed you from {$projectName}",
            'ticket_overdue' => "{$ticketName} is overdue",
            'ticket_nearing_overdue' => "{$ticketName} is due soon",
            'project_overdue' => "{$projectName} is overdue",
            'project_nearing_overdue' => "{$projectName} is due soon",
            default => $this->message ?? '',
        };
    }

    public function getRelativeTimeAttribute(): string
    {
        if (! $this->created_at) {
            return '';
        }

        return Carbon::parse($this->created_at)->diffForHumans();
    }

    public function getActionButtonsAttribute(): array
    {
        $actions = match ($this->type) {
            'comment_added' => [
                $this->makeAction('Reply', 'primary', $this->ticketUrl('#comments')),
                $this->makeAction('View', 'gray', $this->ticketUrl()),
            ],
            'comment_updated' => [
                $this->makeAction('View', 'primary', $this->ticketUrl()),
            ],
            'project_assigned', 'project_nearing_overdue', 'project_overdue' => [
                $this->makeAction('View Project', 'primary', $this->projectUrl()),
            ],
            'ticket_overdue', 'ticket_nearing_overdue' => [
                $this->makeAction('View Ticket', 'primary', $this->ticketUrl()),
            ],
            default => [],
        };

        return array_values(array_filter($actions));
    }

    protected function makeAction(?string $label, string $style, ?string $url): ?array
    {
        if (! $label || ! $url) {
            return null;
        }

        return [
            'label' => $label,
            'style' => $style,
            'url' => $url,
        ];
    }

    protected function ticketUrl(string $fragment = ''): ?string
    {
        $ticketId = $this->data['ticket_id'] ?? null;

        if (! $ticketId) {
            return null;
        }

        $url = route('filament.admin.resources.tickets.view', ['record' => $ticketId]);

        return $fragment ? $url . $fragment : $url;
    }

    protected function projectUrl(): ?string
    {
        $projectId = $this->data['project_id'] ?? null;

        if (! $projectId) {
            return null;
        }

        return route('filament.admin.resources.projects.view', ['record' => $projectId]);
    }
}
