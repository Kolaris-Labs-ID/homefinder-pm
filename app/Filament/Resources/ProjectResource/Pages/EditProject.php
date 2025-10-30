<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Models\Project;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;


    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('external_access')
                ->label('External Dashboard')
                ->icon('heroicon-o-globe-alt')
                ->color('info')
                ->modalHeading('External Dashboard Access')
                ->modalDescription('Share these credentials with external users to access the project dashboard.')
                ->modalContent(function () {
                    $record = $this->record;
                    $externalAccess = $record->externalAccess;
                
                    if (!$externalAccess) {
                        $externalAccess = $record->generateExternalAccess();
                    }
                
                    $dashboardUrl = url('/external/' . $externalAccess->access_token);
                
                    return view('filament.components.external-access-modal', [
                        'dashboardUrl' => $dashboardUrl,
                        'password' => $externalAccess->password,
                        'lastAccessed' => $externalAccess->last_accessed_at ? $externalAccess->last_accessed_at->format('d/m/Y H:i') : null,
                        'isActive' => $externalAccess->is_active,
                    ]);
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalFooterActions([
                    Actions\Action::make('regenerate_external_access')
                        ->label('Regenerate Access')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Regenerate External Access')
                        ->modalDescription('This will generate new credentials and invalidate the current ones.')
                        ->action(function () {
                            $record = $this->record;
                            $record->externalAccess()?->delete();
                            $newAccess = $record->generateExternalAccess();
                            
                            Log::info('Regenerated external access for project: ' . $record->name, [
                                'project_id' => $record->id,
                                'access_token' => $newAccess->access_token,
                                'password' => $newAccess->password
                            ]);
                            
                            Notification::make()
                                ->title('External access regenerated successfully')
                                ->success()
                                ->send();
                        })
                        ->visible(fn () => $this->record->externalAccess !== null),
                ]),
        ];
    }
}
