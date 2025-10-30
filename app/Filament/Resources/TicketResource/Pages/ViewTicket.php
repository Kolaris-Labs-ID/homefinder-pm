<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Pages\ProjectBoard;
use App\Filament\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\TicketComment;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    public ?int $editingCommentId = null;

    protected function getHeaderActions(): array
    {
        $ticket = $this->getRecord();
        $project = $ticket->project;
        
        // Check if user is member of the project
        $canComment = $project->members()->where('users.id', auth()->id())->exists();

        return [
            Actions\EditAction::make()
                ->visible(function () {
                    $ticket = $this->getRecord();

                    return auth()->user()->hasRole(['super_admin'])
                        || $ticket->created_by === auth()->id()
                        || $ticket->assignees()->where('users.id', auth()->id())->exists();
                }),

            Actions\Action::make('addComment')
                ->label('Add Comment')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->form([
                    RichEditor::make('comment')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $ticket = $this->getRecord();

                    $comment = $ticket->comments()->create([
                        'user_id' => auth()->id(),
                        'comment' => $data['comment']
                    ]);

                    // Mark related notifications as read for current user
                    auth()->user()->notifications()
                        ->where('data->ticket_id', $ticket->id)
                        ->whereNull('read_at')
                        ->update(['read_at' => now()]);

                    Notification::make()
                        ->title('Comment added successfully')
                        ->success()
                        ->send();
                })
                ->visible($canComment),

            Action::make('back')
                ->label('Back to Board')
                ->color('gray')
                ->url(fn () => ProjectBoard::getUrl(['project_id' => $this->record->project_id])),
        ];
    }

    public function handleEditComment($id)
    {
        $comment = TicketComment::find($id);

        if (! $comment) {
            Notification::make()
                ->title('Comment not found')
                ->danger()
                ->send();

            return;
        }

        // Check if user can edit (only comment owner or super admin)
        if ($comment->user_id !== auth()->id() && !auth()->user()->hasRole(['super_admin'])) {
            Notification::make()
                ->title('You do not have permission to edit this comment')
                ->danger()
                ->send();

            return;
        }

        $this->editingCommentId = $id;
        $this->mountAction('editComment', ['commentId' => $id]);
    }

    public function handleDeleteComment($id)
    {
        $comment = TicketComment::find($id);

        if (! $comment) {
            Notification::make()
                ->title('Comment not found')
                ->danger()
                ->send();

            return;
        }

        // Check if user can delete (only comment owner or super admin)
        if ($comment->user_id !== auth()->id() && !auth()->user()->hasRole(['super_admin'])) {
            Notification::make()
                ->title('You do not have permission to delete this comment')
                ->danger()
                ->send();

            return;
        }

        $comment->delete();

        Notification::make()
            ->title('Comment deleted successfully')
            ->success()
            ->send();

        // Refresh the page
        $this->redirect($this->getResource()::getUrl('view', ['record' => $this->getRecord()]));
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Grid::make(3)
                    ->schema([
                        Group::make([
                            Section::make()
                                ->schema([
                                    TextEntry::make('uuid')
                                        ->label('Ticket ID')
                                        ->copyable(),

                                    TextEntry::make('name')
                                        ->label('Ticket Name'),

                                    TextEntry::make('project.name')
                                        ->label('Project'),
                                ]),
                        ])->columnSpan(1),

                        Group::make([
                            Section::make()
                                ->schema([
                                    TextEntry::make('status.name')
                                        ->label('Status')
                                        ->badge()
                                        ->color(fn ($state) => match ($state) {
                                            'To Do' => 'warning',
                                            'In Progress' => 'info',
                                            'Review' => 'primary',
                                            'Done' => 'success',
                                            default => 'gray',
                                        }),

                                    // FIXED: Multi-user assignees
                                    TextEntry::make('assignees.name')
                                        ->label('Assigned To')
                                        ->badge()
                                        ->separator(',')
                                        ->default('Unassigned'),

                                    TextEntry::make('creator.name')
                                        ->label('Created By')
                                        ->default('Unknown'),

                                    TextEntry::make('due_date')
                                        ->label('Due Date')
                                        ->date(),
                                ]),
                        ])->columnSpan(1),

                        Group::make([
                            Section::make()
                                ->schema([
                                    TextEntry::make('created_at')
                                        ->label('Created At')
                                        ->dateTime(),

                                    TextEntry::make('updated_at')
                                        ->label('Updated At')
                                        ->dateTime(),

                                    TextEntry::make('epic.name')
                                        ->label('Epic')
                                        ->default('No Epic'),
                                ]),
                        ])->columnSpan(1),
                    ]),

                Section::make('Description')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        TextEntry::make('description')
                            ->hiddenLabel()
                            ->html()
                            ->columnSpanFull(),
                    ]),

                Section::make('Comments')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->description('Discussion about this ticket')
                    ->schema([
                        TextEntry::make('comments_list')
                            ->label('Recent Comments')
                            ->state(function (Ticket $record) {
                                if (method_exists($record, 'comments')) {
                                    return $record->comments()->with('user')->oldest()->get();
                                }

                                return collect();
                            })
                            ->view('filament.resources.ticket-resource.latest-comments'),
                    ])
                    ->collapsible(),

                Section::make('Status History')
                    ->icon('heroicon-o-clock')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('histories')
                            ->hiddenLabel()
                            ->view('filament.resources.ticket-resource.timeline-history'),
                    ]),
            ]);
    }

    protected function getActions(): array
    {
        return [
            Action::make('editComment')
                ->label('Edit Comment')
                ->mountUsing(function (Forms\Form $form, array $arguments) {
                    $commentId = $arguments['commentId'] ?? null;

                    if (! $commentId) {
                        return;
                    }

                    $comment = TicketComment::find($commentId);

                    if (! $comment) {
                        return;
                    }

                    $form->fill([
                        'commentId' => $comment->id,
                        'comment' => $comment->comment,
                    ]);
                })
                ->form([
                    Hidden::make('commentId')
                        ->required(),
                    RichEditor::make('comment')
                        ->label('Comment')
                        ->toolbarButtons([
                            'blockquote',
                            'bold',
                            'bulletList',
                            'codeBlock',
                            'h2',
                            'h3',
                            'italic',
                            'link',
                            'orderedList',
                            'redo',
                            'strike',
                            'underline',
                            'undo',
                        ])
                        ->required(),
                ])
                ->action(function (array $data) {
                    $comment = TicketComment::find($data['commentId']);

                    if (! $comment) {
                        Notification::make()
                            ->title('Comment not found')
                            ->danger()
                            ->send();

                        return;
                    }

                    // Check permissions
                    if (! auth()->user()->can('update', $comment)) {
                        Notification::make()
                            ->title('You do not have permission to edit this comment')
                            ->danger()
                            ->send();

                        return;
                    }

                    $comment->update([
                        'comment' => $data['comment'],
                    ]);

                    Notification::make()
                        ->title('Comment updated successfully')
                        ->success()
                        ->send();

                    // Reset editingCommentId
                    $this->editingCommentId = null;

                    // Refresh the page
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->getRecord()]));
                })
                ->modalWidth('lg')
                ->modalHeading('Edit Comment')
                ->modalSubmitActionLabel('Update')
                ->color('success')
                ->icon('heroicon-o-pencil'),
        ];
    }
}