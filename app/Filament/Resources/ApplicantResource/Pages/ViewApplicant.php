<?php

namespace App\Filament\Resources\ApplicantResource\Pages;

use App\Filament\Resources\ApplicantResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewApplicant extends ViewRecord
{
    protected static string $resource = ApplicantResource::class;

    protected function getHeaderActions(): array
    {
        $hasResume = filled($this->record->resume_file_path);

        return [
            Actions\Action::make('viewResume')
                ->label('View Résumé')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('info')
                ->visible($hasResume && auth()->user()?->can('viewResume', $this->record))
                ->url(fn (): string => route('recruitment.resume.view', $this->record), shouldOpenInNewTab: true),
            Actions\Action::make('downloadResume')
                ->label('Download CV')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible($hasResume && auth()->user()?->can('downloadResume', $this->record))
                ->url(fn (): string => route('recruitment.resume.download', $this->record), shouldOpenInNewTab: true),
            Actions\EditAction::make(),
        ];
    }
}
