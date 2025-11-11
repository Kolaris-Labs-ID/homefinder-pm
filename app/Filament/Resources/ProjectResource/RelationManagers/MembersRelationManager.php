<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Events\ProjectMemberAttached;
use App\Events\ProjectMemberDetached;
use App\Helpers\RoleHelper;
use App\Models\User;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return $ownerRecord->members_count ?? $ownerRecord->members()->count();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([

            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('Add Member')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'email'])
                    ->recordSelectOptionsQuery(
                        fn (Builder $query) => RoleHelper::scopeAllowedAssignees($query)
                    )
                    ->after(function (Model $record) {
                        try {
                            $project = $this->getOwnerRecord();
                            // $record is the User model that was attached
                            $user = $record instanceof User ? $record : User::find($record->id ?? $record->getKey());
                            $assignedBy = auth()->user();
                            
                            if (!$user || !$assignedBy) {
                                return;
                            }
                            
                            // Validate role-based assignment (double-check)
                            if (!RoleHelper::canAssignTo($user, $assignedBy)) {
                                Notification::make()
                                    ->danger()
                                    ->title('Assignment not allowed')
                                    ->body('You do not have permission to assign this user based on your role.')
                                    ->send();
                                
                                // Detach the user if they were added
                                $project->members()->detach($user->id);
                                return;
                            }
                            
                            ProjectMemberAttached::dispatch($project, $user, $assignedBy);
                        } catch (\Exception $e) {
                            // Log the error but don't break the attach action
                            \Log::error('Error in MembersRelationManager attach after callback: ' . $e->getMessage(), [
                                'exception' => $e,
                                'record_id' => $record->id ?? null,
                            ]);
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\DetachAction::make()
                    ->label('Remove')
                    ->after(function (Model $record) {
                        try {
                            $project = $this->getOwnerRecord();
                            // $record is the User model that was detached
                            $user = $record instanceof User ? $record : User::find($record->id ?? $record->getKey());
                            $removedBy = auth()->user();
                            
                            if ($user && $removedBy) {
                                ProjectMemberDetached::dispatch($project, $user, $removedBy);
                            }
                        } catch (\Exception $e) {
                            // Log the error but don't break the detach action
                            \Log::error('Error in MembersRelationManager detach after callback: ' . $e->getMessage(), [
                                'exception' => $e,
                                'record_id' => $record->id ?? null,
                            ]);
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make()
                        ->label('Remove Selected'),
                ]),
            ]);
    }
}