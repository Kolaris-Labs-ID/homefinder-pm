<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\TicketResource;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Ticket;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RecentActivityTable extends BaseWidget
{
    use HasWidgetShield;

    protected static ?string $heading = 'Recent Activities';

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'md' => 6,
        'lg' => 6,
        'xl' => 6,
    ];

    protected static ?int $sort = 1;

    public function table(Table $table): Table
    {
        $currentUser = Auth::user();

        return $table
            ->query(
                ActivityLog::query()
                    ->with(['project', 'user', 'subject'])
                    ->when(!$currentUser?->hasRole('super_admin'), function ($query) {
                        $query->whereHas('project.members', function ($subQuery) {
                            $subQuery->where('user_id', Auth::id());
                        });
                    })
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('activity_summary')
                    ->label('Activity')
                    ->state(fn (ActivityLog $record): string => $this->formatActivitySummary($record))
                    ->description(fn (ActivityLog $record): string => $this->formatActivityDescription($record))
                    ->html()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $subQuery) use ($search) {
                            $subQuery
                                ->whereHas('user', fn (Builder $userQuery) => $userQuery->where('name', 'like', "%{$search}%"))
                                ->orWhereHas('project', fn (Builder $projectQuery) => $projectQuery->where('name', 'like', "%{$search}%"))
                                ->orWhereHasMorph(
                                    'subject',
                                    [Ticket::class],
                                    fn (Builder $ticketQuery) => $ticketQuery
                                        ->where('name', 'like', "%{$search}%")
                                        ->orWhere('uuid', 'like', "%{$search}%")
                                )
                                ->orWhereHasMorph(
                                    'subject',
                                    [Project::class],
                                    fn (Builder $projectSubjectQuery) => $projectSubjectQuery
                                        ->where('name', 'like', "%{$search}%")
                                        ->orWhere('ticket_prefix', 'like', "%{$search}%")
                                )
                                ->orWhere('event_type', 'like', "%{$search}%");
                        });
                    })
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Type')
                    ->badge()
                    ->state(fn (ActivityLog $record): string => $this->getEventLabel($record))
                    ->icon(fn (ActivityLog $record): string => $this->getEventIcon($record))
                    ->iconPosition('before')
                    ->alignEnd()
                    ->color(fn (ActivityLog $record): string => $this->getEventColor($record)),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('start_date')
                            ->label('Start Date')
                            ->default(today()),
                        \Filament\Forms\Components\DatePicker::make('end_date')
                            ->label('End Date')
                            ->default(today()),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['start_date'], function ($query, $date) {
                                $query->whereDate('created_at', '>=', $date);
                            })
                            ->when($data['end_date'], function ($query, $date) {
                                $query->whereDate('created_at', '<=', $date);
                            });
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['start_date'] ?? null) {
                            $indicators[] = 'From: ' . \Carbon\Carbon::parse($data['start_date'])->format('M d, Y');
                        }
                        if ($data['end_date'] ?? null) {
                            $indicators[] = 'To: ' . \Carbon\Carbon::parse($data['end_date'])->format('M d, Y');
                        }
                        return $indicators;
                    }),

                Tables\Filters\Filter::make('today')
                    ->label('Today Only')
                    ->query(fn ($query) => $query->whereDate('created_at', today()))
                    ->toggle(),

                SelectFilter::make('event_type')
                    ->label('Activity Type')
                    ->options([
                        'ticket_created' => 'Ticket Created',
                        'ticket_updated' => 'Ticket Edited',
                        'ticket_status_changed' => 'Status Changed',
                        'ticket_comment_created' => 'New Comment',
                        'project_created' => 'Project Created',
                    ]),

                Tables\Filters\SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->filtersFormColumns(2)
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->size('sm')
                    ->tooltip('Open Record')
                    ->visible(fn (ActivityLog $record): bool => filled($this->getRecordUrl($record)))
                    ->url(fn (ActivityLog $record): ?string => $this->getRecordUrl($record))
                    ->openUrlInNewTab()
            ])
            ->recordUrl(fn (ActivityLog $record): ?string => $this->getRecordUrl($record))
            ->paginated([5, 25, 50])
            ->poll('30s')
            ->striped()
            ->emptyStateHeading('No Activity Found')
            ->emptyStateDescription('No project or ticket activities found for the selected period.')
            ->emptyStateIcon('heroicon-o-clock');
    }

    private function formatActivitySummary(ActivityLog $record): string
    {
        $userName = e($record->user->name ?? 'System');
        $subjectName = e(Str::limit($record->meta['name'] ?? $record->subject?->name ?? 'Unknown item', 40));
        $toStatus = e($record->meta['to_status_name'] ?? 'Unknown status');

        return match ($record->event_type) {
            'ticket_created' => "<span class='text-primary-600 font-medium'>{$userName}</span> created ticket \"{$subjectName}\"",
            'ticket_updated' => "<span class='text-primary-600 font-medium'>{$userName}</span> updated ticket \"{$subjectName}\"",
            'ticket_status_changed' => "<span class='text-primary-600 font-medium'>{$userName}</span> moved \"{$subjectName}\" to {$toStatus}",
            'ticket_comment_created' => "<span class='text-primary-600 font-medium'>{$userName}</span> commented on ticket \"{$subjectName}\"",
            'project_created' => "<span class='text-primary-600 font-medium'>{$userName}</span> created project \"{$subjectName}\"",
            default => "<span class='text-primary-600 font-medium'>{$userName}</span> updated \"{$subjectName}\"",
        };
    }

    private function formatActivityDescription(ActivityLog $record): string
    {
        $isToday = $record->created_at->isToday();
        $time = $isToday
            ? $record->created_at->format('H:i')
            : $record->created_at->format('M d, H:i');

        if ($record->event_type === 'project_created') {
            $ticketPrefix = $record->meta['ticket_prefix'] ?? $record->subject?->ticket_prefix ?? 'Project';

            return "{$time} • {$ticketPrefix} • New project";
        }

        $project = $record->project->name ?? 'No Project';
        $uuid = $record->meta['uuid'] ?? $record->subject?->uuid ?? '';
        $description = trim("{$time} • {$uuid} • {$project}", ' •');

        if ($record->event_type !== 'ticket_updated') {
            return $description;
        }

        $fieldSummary = $this->formatChangedFields($record);

        return $fieldSummary !== null
            ? "{$description} • Edited: {$fieldSummary}"
            : $description;
    }

    private function formatChangedFields(ActivityLog $record): ?string
    {
        $changedFields = collect($record->meta['changed_fields'] ?? [])
            ->map(fn (string $field): string => match ($field) {
                'name' => 'Name',
                'description' => 'Description',
                'start_date' => 'Start Date',
                'due_date' => 'Due Date',
                'priority_id' => 'Priority',
                'project_id' => 'Project',
                'epic_id' => 'Epic',
                'archived_at' => 'Archive Status',
                'assignees' => 'Assignees',
                default => Str::headline($field),
            })
            ->take(3)
            ->values();

        if ($changedFields->isEmpty()) {
            return null;
        }

        $summary = $changedFields->implode(', ');
        $extraCount = count($record->meta['changed_fields'] ?? []) - $changedFields->count();

        if ($extraCount > 0) {
            $summary .= " +{$extraCount} more";
        }

        return $summary;
    }

    private function getEventLabel(ActivityLog $record): string
    {
        return match ($record->event_type) {
            'ticket_created' => 'Ticket Created',
            'ticket_updated' => 'Ticket Edited',
            'ticket_status_changed' => 'Status Changed',
            'ticket_comment_created' => 'New Comment',
            'project_created' => 'Project Created',
            default => 'Activity',
        };
    }

    private function getEventColor(ActivityLog $record): string
    {
        return match ($record->event_type) {
            'ticket_created' => 'success',
            'ticket_updated' => 'info',
            'ticket_status_changed' => 'warning',
            'ticket_comment_created' => 'fuchsia',
            'project_created' => 'primary',
            default => 'gray',
        };
    }

    private function getEventIcon(ActivityLog $record): string
    {
        return match ($record->event_type) {
            'ticket_created' => 'heroicon-m-plus-circle',
            'ticket_updated' => 'heroicon-m-pencil-square',
            'ticket_status_changed' => 'heroicon-m-arrows-right-left',
            'ticket_comment_created' => 'heroicon-m-chat-bubble-left-right',
            'project_created' => 'heroicon-m-folder-plus',
            default => 'heroicon-m-information-circle',
        };
    }

    private function getRecordUrl(ActivityLog $record): ?string
    {
        return match ($record->subject_type) {
            Ticket::class => TicketResource::getUrl('view', ['record' => $record->subject_id]),
            Project::class => ProjectResource::getUrl('view', ['record' => $record->subject_id]),
            default => null,
        };
    }
}
