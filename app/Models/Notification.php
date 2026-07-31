<?php

namespace App\Models;

use Carbon\Carbon;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'pm_notifications';

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

    /**
     * Mirror every notification into Laravel's database notifications so the
     * browser-push plugin (which listens on DatabaseNotification::created)
     * delivers it as a native browser notification.
     */
    protected static function booted(): void
    {
        static::created(function (self $notification) {
            if (! $notification->user) {
                return;
            }

            $filament = FilamentNotification::make()
                ->title($notification->title)
                ->body($notification->human_message);

            if ($url = $notification->action_buttons[0]['url'] ?? null) {
                $filament->actions([Action::make('view')->url($url)]);
            }

            $filament->sendToDatabase($notification->user);
        });
    }

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
        $requestorName = $this->data['requestor_name'] ?? null;
        $approvableType = isset($this->data['approvable_type']) ? class_basename($this->data['approvable_type']) : 'Request';

        return match ($this->type) {
            'comment_added' => "{$actor} replied on {$ticketName}",
            'comment_updated' => "{$actor} updated a comment on {$ticketName}",
            'project_assigned' => "{$actor} added you to {$projectName}",
            'project_removed' => "{$actor} removed you from {$projectName}",
            'ticket_overdue' => "{$ticketName} is overdue",
            'ticket_nearing_overdue' => "{$ticketName} is due soon",
            'project_overdue' => "{$projectName} is overdue",
            'project_nearing_overdue' => "{$projectName} is due soon",
            'approval_pending' => $requestorName
                ? "{$approvableType} from {$requestorName} requires your approval"
                : "{$approvableType} requires your approval",
            'approval_approved' => "Your {$approvableType} has been approved",
            'approval_rejected' => "Your {$approvableType} has been rejected",
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
            'approval_pending' => [
                $this->makeAction('Review', 'primary', $this->approvableUrl()),
                $this->makeAction('Dashboard', 'gray', $this->approvalDashboardUrl()),
            ],
            'approval_approved', 'approval_rejected' => [
                $this->makeAction('View Details', 'primary', $this->approvableUrl()),
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

    protected function approvableUrl(): ?string
    {
        // First check if URL is directly provided in data
        if (isset($this->data['approvable_url']) && $this->data['approvable_url']) {
            return $this->data['approvable_url'];
        }

        // Fallback to generating URL from approvable type and id
        $approvableType = $this->data['approvable_type'] ?? null;
        $approvableId = $this->data['approvable_id'] ?? null;

        if (! $approvableType || ! $approvableId) {
            return null;
        }

        try {
            return match ($approvableType) {
                \App\Models\Hris\LeaveRequest::class => route('filament.admin.resources.leave-requests.edit', ['record' => $approvableId]),
                \App\Models\Hris\Reimbursement::class => route('filament.admin.resources.reimbursements.edit', ['record' => $approvableId]),
                \App\Models\Hris\PayrollRecord::class => route('filament.admin.resources.payrun-batches.index'),
                default => null,
            };
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function approvalDashboardUrl(): ?string
    {
        try {
            return route('filament.admin.pages.approval-dashboard');
        } catch (\Exception $e) {
            return null;
        }
    }
}
