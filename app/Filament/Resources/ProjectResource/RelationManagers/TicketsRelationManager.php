<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\Epic;
use App\Models\TicketStatus;
use App\Models\TicketPriority;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Imports\TicketsImport;
use App\Exports\TicketTemplateExport;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;

class TicketsRelationManager extends RelationManager
{
    protected static string $relationship = 'tickets';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return $ownerRecord->tickets_count ?? $ownerRecord->tickets()->count();
    }

    public function form(Form $form): Form
    {
        $projectId = $this->getOwnerRecord()->id;

        $defaultStatus = TicketStatus::where('project_id', $projectId)->first();
        $defaultStatusId = $defaultStatus ? $defaultStatus->id : null;

        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->label('Ticket Name'),
                
                Forms\Components\Select::make('ticket_status_id')
                    ->label('Status')
                    ->options(function () use ($projectId) {
                        return TicketStatus::where('project_id', $projectId)
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->default($defaultStatusId)
                    ->required()
                    ->searchable(),
                
                Forms\Components\Select::make('epic_id')
                    ->label('Epic')
                    ->options(function () use ($projectId) {
                        return Epic::where('project_id', $projectId)
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->nullable(),
                
                // UPDATED: Multi-user assignment
                Forms\Components\Select::make('assignees')
                    ->label('Assignees')
                    ->multiple()
                    ->relationship(
                        name: 'assignees',
                        titleAttribute: 'name',
                        modifyQueryUsing: function ($query) {
                            $projectId = $this->getOwnerRecord()->id;
                            // Only show project members
                            return $query->whereHas('projects', function ($query) use ($projectId) {
                                $query->where('projects.id', $projectId);
                            });
                        }
                    )
                    ->searchable()
                    ->preload()
                    ->default(function ($record) {
                        if ($record && $record->exists) {
                            return $record->assignees->pluck('id')->toArray();
                        }
                        
                        // Auto-assign current user if they're a project member
                        $project = $this->getOwnerRecord();
                        $isCurrentUserMember = $project->members()->where('users.id', auth()->id())->exists();
                        
                        return $isCurrentUserMember ? [auth()->id()] : [];
                    })
                    ->helperText('Select multiple users to assign this ticket to. Only project members can be assigned.'),
                
                Forms\Components\DatePicker::make('start_date')
                    ->label('Start Date')
                    ->nullable(),
                
                Forms\Components\DatePicker::make('due_date')
                    ->label('Due Date')
                    ->nullable()
                    ->afterOrEqual('start_date'),
                
                Forms\Components\RichEditor::make('description')
                    ->columnSpanFull()
                    ->nullable(),

                // Show created by in edit mode
                Forms\Components\Select::make('created_by')
                    ->label('Created By')
                    ->relationship('creator', 'name')
                    ->disabled()
                    ->hiddenOn('create'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('uuid')
                    ->label('Ticket ID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('status.name')
                    ->badge()
                    ->color(fn ($record) => match ($record->status?->name) {
                        'To Do' => 'warning',
                        'In Progress' => 'info',
                        'Review' => 'primary',
                        'Done' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('epic.name')
                    ->label('Epic')
                    ->badge()
                    ->color('warning')
                    ->placeholder('No Epic')
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('assignees.name')
                    ->label('Assignees')
                    ->badge()
                    ->separator(',')
                    ->expandableLimitedList()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created By')
                    ->sortable()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('ticket_status_id')
                    ->label('Status')
                    ->options(function () {
                        $projectId = $this->getOwnerRecord()->id;

                        return TicketStatus::where('project_id', $projectId)
                            ->pluck('name', 'id')
                            ->toArray();
                    }),
                
                // UPDATED: Filter by assignees
                Tables\Filters\SelectFilter::make('assignees')
                    ->label('Assignee')
                    ->relationship('assignees', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
                
                // Filter by creator
                Tables\Filters\SelectFilter::make('created_by')
                    ->label('Created By')
                    ->relationship('creator', 'name')
                    ->searchable()
                    ->preload(),
                
                // Filter by epic
                Tables\Filters\SelectFilter::make('epic_id')
                    ->label('Epic')
                    ->options(function () {
                        $projectId = $this->getOwnerRecord()->id;
                        return Epic::where('project_id', $projectId)
                            ->pluck('name', 'id')
                            ->toArray();
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        // Set project_id and created_by
                        $data['project_id'] = $this->getOwnerRecord()->id;
                        $data['created_by'] = auth()->id();
                        return $data;
                    }),
                
                // NEW: Import from Excel action
                Tables\Actions\Action::make('import_tickets')
                    ->label('Import from Excel')
                    ->icon('heroicon-m-arrow-up-tray')
                    ->color('success')
                    ->form([
                        Section::make('Import Tickets from Excel')
                            ->description('Upload an Excel file to import tickets to this project. You can download the template below.')
                            ->schema([
                                Actions::make([
                                    FormAction::make('download_template')
                                        ->label('Download Import Template')
                                        ->icon('heroicon-m-arrow-down-tray')
                                        ->color('gray')
                                        ->action(function (RelationManager $livewire) {
                                            $project = $livewire->getOwnerRecord();
                                            $filename = 'ticket-import-template-' . str($project->name)->slug() . '.xlsx';
                                            
                                            return Excel::download(
                                                new TicketTemplateExport($project),
                                                $filename
                                            );
                                        })
                                ])->fullWidth(),
                                
                                FileUpload::make('excel_file')
                                    ->label('Excel File')
                                    ->helperText('Upload the Excel file with ticket data. Make sure to use the template format above.')
                                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])
                                    ->maxSize(5120) // 5MB
                                    ->required()
                                    ->disk('local')
                                    ->directory('temp-imports')
                                    ->visibility('private'),
                            ]),
                    ])
                    ->action(function (array $data, RelationManager $livewire) {
                        $project = $livewire->getOwnerRecord();
                        $filePath = Storage::disk('local')->path($data['excel_file']);
                        
                        try {
                            $import = new TicketsImport($project);
                            Excel::import($import, $filePath);
                            
                            $importedCount = $import->getImportedCount();
                            $errors = $import->errors();
                            $failures = $import->failures();
                            
                            // Clean up uploaded file
                            Storage::disk('local')->delete($data['excel_file']);
                            
                            if ($importedCount > 0) {
                                $message = "Successfully imported {$importedCount} ticket(s) to project '{$project->name}'.";
                                
                                if (count($errors) > 0 || count($failures) > 0) {
                                    $message .= " Some rows had errors and were skipped.";
                                }
                                
                                Notification::make()
                                    ->title('Import Completed')
                                    ->body($message)
                                    ->success()
                                    ->send();
                            } else {
                                // Get actual errors and failures
                                $importErrors = $import->errors();
                                $importFailures = $import->failures();
                                
                                $errorMessage = "No tickets were imported.";
                                
                                // Show actual validation failures if they exist
                                if (!empty($importFailures)) {
                                    $errorMessage .= "\n\n**Validation Errors:**";
                                    foreach ($importFailures as $failure) {
                                        $row = $failure->row();
                                        $errors = implode(', ', $failure->errors());
                                        $errorMessage .= "\n• Row {$row}: {$errors}";
                                    }
                                }
                                
                                // Show actual processing errors if they exist
                                if (!empty($importErrors)) {
                                    $errorMessage .= "\n\n**Processing Errors:**";
                                    foreach ($importErrors as $error) {
                                        $errorMessage .= "\n• {$error}";
                                    }
                                }
                                
                                // Only show generic help if no specific errors are available
                                if (empty($importFailures) && empty($importErrors)) {
                                    $errorMessage .= "\n\n**Possible causes:**";
                                    $errorMessage .= "\n• File contains only headers or sample data";
                                    $errorMessage .= "\n• All rows were skipped due to validation issues";
                                    $errorMessage .= "\n• File format is incorrect";
                                    $errorMessage .= "\n\nPlease check your file and try again.";
                                }
                                
                                Notification::make()
                                    ->title('Import Failed')
                                    ->body($errorMessage)
                                    ->warning()
                                    ->persistent()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            // Clean up uploaded file on error
                            Storage::disk('local')->delete($data['excel_file']);
                            
                            Notification::make()
                                ->title('Import Error')
                                ->body('An error occurred during import: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('updateStatus')
                        ->label('Update Status')
                        ->icon('heroicon-o-arrow-path')
                        ->form([
                            Forms\Components\Select::make('ticket_status_id')
                                ->label('Status')
                                ->options(function (RelationManager $livewire) {
                                    $projectId = $livewire->getOwnerRecord()->id;

                                    return TicketStatus::where('project_id', $projectId)
                                        ->pluck('name', 'id')
                                        ->toArray();
                                })
                                ->required(),
                        ])
                        ->action(function (array $data, Collection $records) {
                            foreach ($records as $record) {
                                $record->update([
                                    'ticket_status_id' => $data['ticket_status_id'],
                                ]);
                            }
                            
                            Notification::make()
                                ->success()
                                ->title('Status updated')
                                ->body(count($records) . ' tickets have been updated.')
                                ->send();
                        }),
                    
                    // NEW: Bulk assign users
                    Tables\Actions\BulkAction::make('assignUsers')
                        ->label('Assign Users')
                        ->icon('heroicon-o-user-plus')
                        ->form([
                            Forms\Components\Select::make('assignees')
                                ->label('Assignees')
                                ->multiple()
                                ->options(function (RelationManager $livewire) {
                                    return $livewire->getOwnerRecord()
                                        ->members()
                                        ->pluck('name', 'users.id')
                                        ->toArray();
                                })
                                ->searchable()
                                ->preload()
                                ->required(),
                            
                            Forms\Components\Radio::make('assignment_mode')
                                ->label('Assignment Mode')
                                ->options([
                                    'replace' => 'Replace existing assignees',
                                    'add' => 'Add to existing assignees',
                                ])
                                ->default('add')
                                ->required(),
                        ])
                        ->action(function (array $data, Collection $records) {
                            foreach ($records as $record) {
                                if ($data['assignment_mode'] === 'replace') {
                                    $record->assignees()->sync($data['assignees']);
                                } else {
                                    $record->assignees()->syncWithoutDetaching($data['assignees']);
                                }
                            }
                            
                            Notification::make()
                                ->success()
                                ->title('Users assigned')
                                ->body(count($records) . ' tickets have been updated with new assignees.')
                                ->send();
                        }),
                    Tables\Actions\BulkAction::make('updatePriority')
                        ->label('Update Priority')
                        ->icon('heroicon-o-flag')
                        ->form([
                            Forms\Components\Select::make('priority_id')
                                ->label('Priority')
                                ->options(TicketPriority::pluck('name', 'id')->toArray())
                                ->nullable(),
                        ])
                        ->action(function (array $data, Collection $records) {
                            foreach ($records as $record) {
                                $record->update([
                                    'priority_id' => $data['priority_id'],
                                ]);
                            }
                        }),
                    
                    Tables\Actions\BulkAction::make('assignToEpic')
                        ->label('Assign to Epic')
                        ->icon('heroicon-o-bookmark')
                        ->form([
                            Forms\Components\Select::make('epic_id')
                                ->label('Epic')
                                ->options(function (RelationManager $livewire) {
                                    $projectId = $livewire->getOwnerRecord()->id;
                                    return Epic::where('project_id', $projectId)
                                        ->pluck('name', 'id')
                                        ->toArray();
                                })
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->helperText('Select an epic to assign the selected tickets to. Leave empty to remove epic assignment.'),
                        ])
                        ->action(function (array $data, Collection $records) {
                            foreach ($records as $record) {
                                $record->update([
                                    'epic_id' => $data['epic_id'],
                                ]);
                            }
                            
                            $epicName = $data['epic_id'] 
                                ? Epic::find($data['epic_id'])->name 
                                : 'No Epic';
                            
                            Notification::make()
                                ->success()
                                ->title('Epic assignment updated')
                                ->body(count($records) . ' tickets have been assigned to: ' . $epicName)
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}