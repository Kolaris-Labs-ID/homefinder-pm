<?php

namespace App\Filament\Resources\ApplicantResource\Pages;

use App\Filament\Resources\ApplicantResource;
use App\Services\Hris\Screening\ScreeningOrchestrator;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewApplicant extends ViewRecord
{
    protected static string $resource = ApplicantResource::class;

    protected function getHeaderActions(): array
    {
        $hasResume = filled($this->record->resume_file_path);
        $canViewScreening = auth()->user()?->can('viewScreening', $this->record) ?? false;

        return [
            Actions\Action::make('viewResume')
                ->label('View Résumé')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('info')
                ->visible($hasResume && (auth()->user()?->can('viewResume', $this->record) ?? false))
                ->url(fn (): string => route('recruitment.resume.view', $this->record), shouldOpenInNewTab: true),
            Actions\Action::make('downloadResume')
                ->label('Download CV')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible($hasResume && (auth()->user()?->can('downloadResume', $this->record) ?? false))
                ->url(fn (): string => route('recruitment.resume.download', $this->record), shouldOpenInNewTab: true),
            Actions\Action::make('viewScreening')
                ->label('Screening Detail')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('gray')
                ->visible($canViewScreening)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->infolist([
                    Infolists\Components\Section::make('ATS Screening (advisory)')
                        ->description('Rule-based screening — advisory only; it does not change the applicant status.')
                        ->columns(2)
                        ->schema([
                            Infolists\Components\TextEntry::make('ats_score')
                                ->label('Score')
                                ->getStateUsing(fn (): string => (string) ($this->record->latestScreeningResult()?->score ?? '—')),
                            Infolists\Components\TextEntry::make('ats_recommendation')
                                ->label('Recommendation')
                                ->badge()
                                ->getStateUsing(fn (): string => ucwords(str_replace('_', ' ', $this->record->effectiveScreeningRecommendation() ?? 'not_screened'))),
                            Infolists\Components\TextEntry::make('ats_matched')
                                ->label('Matched criteria')
                                ->getStateUsing(fn (): string => collect($this->record->latestScreeningResult()?->matched_criteria ?? [])->pluck('label')->implode(', ') ?: '—')
                                ->columnSpanFull(),
                            Infolists\Components\TextEntry::make('ats_missing')
                                ->label('Missing criteria')
                                ->getStateUsing(fn (): string => collect($this->record->latestScreeningResult()?->missing_criteria ?? [])->pluck('label')->implode(', ') ?: '—')
                                ->columnSpanFull(),
                            Infolists\Components\TextEntry::make('ats_flags')
                                ->label('Flags')
                                ->getStateUsing(fn (): string => collect($this->record->latestScreeningResult()?->flags ?? [])->pluck('code')->implode(', ') ?: '—')
                                ->columnSpanFull(),
                            Infolists\Components\TextEntry::make('ats_summary')
                                ->label('Redacted summary')
                                ->getStateUsing(fn (): string => $this->record->latestScreeningResult()?->parsed_summary ?? '—')
                                ->columnSpanFull(),
                        ]),
                ]),
            Actions\Action::make('runScreening')
                ->label(fn (): string => $this->record->latestScreeningResult() ? 'Re-run Screening' : 'Run Screening')
                ->icon('heroicon-o-sparkles')
                ->color('info')
                ->requiresConfirmation()
                ->authorize(fn (): bool => auth()->user()?->can('runScreening', $this->record) ?? false)
                ->action(function (): void {
                    app(ScreeningOrchestrator::class)->runScreening($this->record, auth()->user(), manual: true);
                    Notification::make()->title('Screening queued')->success()->send();
                }),
            Actions\Action::make('overrideScreening')
                ->label('Override Recommendation')
                ->icon('heroicon-o-hand-raised')
                ->color('warning')
                ->authorize(fn (): bool => auth()->user()?->can('overrideScreening', $this->record) ?? false)
                ->form([
                    Forms\Components\Select::make('recommendation')
                        ->options([
                            'strong_match' => 'Strong match',
                            'review' => 'Review',
                            'low_match' => 'Low match',
                            'needs_manual_review' => 'Needs manual review',
                        ])
                        ->required(),
                    Forms\Components\Textarea::make('note')->label('Reason (required)')->required()->rows(3),
                ])
                ->action(function (array $data): void {
                    app(ScreeningOrchestrator::class)->override($this->record, $data['recommendation'], $data['note'], auth()->user());
                    Notification::make()->title('Override applied')->success()->send();
                }),
            Actions\Action::make('clearScreeningOverride')
                ->label('Clear Override')
                ->icon('heroicon-o-x-mark')
                ->color('gray')
                ->visible(fn (): bool => $this->record->activeScreeningOverride()->exists())
                ->authorize(fn (): bool => auth()->user()?->can('clearScreeningOverride', $this->record) ?? false)
                ->form([
                    Forms\Components\Textarea::make('note')->label('Clear note (required)')->required()->rows(2),
                ])
                ->action(function (array $data): void {
                    app(ScreeningOrchestrator::class)->clearOverride($this->record, $data['note'], auth()->user());
                    Notification::make()->title('Override cleared')->success()->send();
                }),
            Actions\EditAction::make(),
        ];
    }
}
