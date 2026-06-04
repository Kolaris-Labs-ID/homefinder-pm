<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApplicantResource\Pages;
use App\Models\Hris\Applicant;
use App\Models\Hris\Interview;
use App\Models\User;
use App\Services\Hris\RecruitmentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ApplicantResource extends Resource
{
    protected static ?string $model = Applicant::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationLabel = 'Applicants';

    protected static ?string $navigationGroup = 'Recruitment';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Applicant Information')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(50),
                        Forms\Components\Select::make('job_posting_id')
                            ->label('Job Posting')
                            ->relationship('jobPosting', 'title')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ]),

                Forms\Components\Section::make('Application Documents')
                    ->schema([
                        Forms\Components\FileUpload::make('resume_file_path')
                            ->label('Resume (PDF only, max 1 MB)')
                            ->disk('private')
                            ->directory('applicants/resumes')
                            ->visibility('private')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(1024),
                        Forms\Components\Placeholder::make('resume_meta')
                            ->label('Résumé file')
                            ->visible(fn (?Applicant $record): bool => filled($record?->resume_file_path))
                            ->content(fn (?Applicant $record): string => trim(sprintf(
                                '%s · %s · uploaded %s',
                                $record?->resume_original_filename ?: 'resume.pdf',
                                $record?->resume_size_bytes ? round($record->resume_size_bytes / 1024).' KB' : '—',
                                optional($record?->resume_uploaded_at)->format('d M Y H:i') ?: '—',
                            ), ' ·')),
                        Forms\Components\Textarea::make('cover_letter')
                            ->rows(5)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options(self::statusLabels())
                            ->default(Applicant::STATUS_APPLIED)
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->rows(3)
                            ->visible(fn (?Applicant $record): bool => $record?->isRejected() ?? false),
                    ])
                    ->visibleOn('edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('jobPosting.title')
                    ->label('Job Posting')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => [Applicant::STATUS_APPLIED, Applicant::STATUS_WITHDRAWN],
                        'info' => [Applicant::STATUS_SCREENING, Applicant::STATUS_SHORTLISTED],
                        'warning' => [Applicant::STATUS_INTERVIEW_SCHEDULED, Applicant::STATUS_INTERVIEWED],
                        'primary' => Applicant::STATUS_OFFERED,
                        'success' => Applicant::STATUS_HIRED,
                        'danger' => Applicant::STATUS_REJECTED,
                    ])
                    ->formatStateUsing(fn (string $state): string => self::statusLabels()[$state] ?? ucfirst($state)),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('phone')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Applied Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('job_posting_id')
                    ->label('Job Posting')
                    ->relationship('jobPosting', 'title'),
                Tables\Filters\SelectFilter::make('status')
                    ->options(self::statusLabels()),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('advance')
                        ->label(fn (Applicant $record): string => 'Advance to '.ucfirst($record->getNextStatus() ?? ''))
                        ->icon('heroicon-o-arrow-right-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (Applicant $record): bool => $record->canAdvance() && $record->getNextStatus() !== null)
                        ->action(function (Applicant $record): void {
                            $service = app(RecruitmentService::class);
                            $nextStatus = $record->getNextStatus();
                            if ($nextStatus) {
                                $service->advanceApplicant($record, $nextStatus);
                                Notification::make()
                                    ->title('Applicant advanced to '.ucfirst($nextStatus))
                                    ->success()
                                    ->send();
                            }
                        }),
                    Tables\Actions\Action::make('reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->form([
                            Forms\Components\Textarea::make('rejection_reason')
                                ->label('Rejection Reason')
                                ->required()
                                ->rows(3),
                        ])
                        ->visible(fn (Applicant $record): bool => $record->isActive())
                        ->action(function (Applicant $record, array $data): void {
                            $service = app(RecruitmentService::class);
                            $service->rejectApplicant($record, $data['rejection_reason']);
                            Notification::make()
                                ->title('Applicant rejected')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('hire')
                        ->label('Hire')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->form([
                            Forms\Components\Select::make('employee_id')
                                ->label('Link to Employee')
                                ->options(fn (): array => Employee::with('user')->get()
                                    ->mapWithKeys(fn (Employee $e): array => [
                                        $e->id => ($e->user?->name ?? 'Employee').' (NIK '.($e->nik ?? '—').')',
                                    ])
                                    ->all())
                                ->searchable()
                                ->required()
                                ->helperText('An applicant can only become "hired" once linked to an Employee record.'),
                        ])
                        ->visible(fn (Applicant $record): bool => $record->isOffered())
                        ->action(function (Applicant $record, array $data): void {
                            $service = app(RecruitmentService::class);
                            $service->hireApplicant($record, Employee::findOrFail($data['employee_id']));
                            Notification::make()
                                ->title('Applicant hired and linked to employee')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('withdraw')
                        ->label('Withdraw')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->visible(fn (Applicant $record): bool => $record->isActive())
                        ->action(function (Applicant $record): void {
                            $service = app(RecruitmentService::class);
                            $service->withdrawApplicant($record);
                            Notification::make()
                                ->title('Applicant withdrawn')
                                ->success()
                                ->send();
                        }),
                    Tables\Actions\Action::make('schedule_interview')
                        ->label('Schedule Interview')
                        ->icon('heroicon-o-calendar')
                        ->color('info')
                        ->form([
                            Forms\Components\Select::make('interviewer_id')
                                ->label('Interviewer')
                                ->options(User::pluck('name', 'id'))
                                ->searchable()
                                ->required(),
                            Forms\Components\DateTimePicker::make('scheduled_at')
                                ->label('Scheduled At')
                                ->required()
                                ->minDate(now()),
                            Forms\Components\TextInput::make('location')
                                ->maxLength(255),
                            Forms\Components\Select::make('interview_type')
                                ->label('Interview Type')
                                ->options([
                                    Interview::TYPE_PHONE => 'Phone',
                                    Interview::TYPE_VIDEO => 'Video',
                                    Interview::TYPE_ONSITE => 'Onsite',
                                ])
                                ->default(Interview::TYPE_ONSITE)
                                ->required(),
                        ])
                        ->visible(fn (Applicant $record): bool => $record->isActive())
                        ->action(function (Applicant $record, array $data): void {
                            $service = app(RecruitmentService::class);
                            $service->scheduleInterview($record, $data);
                            Notification::make()
                                ->title('Interview scheduled')
                                ->success()
                                ->send();
                        }),
                ]),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Human-readable labels for every applicant status.
     */
    public static function statusLabels(): array
    {
        return [
            Applicant::STATUS_APPLIED => 'Applied',
            Applicant::STATUS_SCREENING => 'Screening',
            Applicant::STATUS_SHORTLISTED => 'Shortlisted',
            Applicant::STATUS_INTERVIEW_SCHEDULED => 'Interview Scheduled',
            Applicant::STATUS_INTERVIEWED => 'Interviewed',
            Applicant::STATUS_OFFERED => 'Offered',
            Applicant::STATUS_HIRED => 'Hired',
            Applicant::STATUS_REJECTED => 'Rejected',
            Applicant::STATUS_WITHDRAWN => 'Withdrawn',
        ];
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApplicants::route('/'),
            'create' => Pages\CreateApplicant::route('/create'),
            'view' => Pages\ViewApplicant::route('/{record}'),
            'edit' => Pages\EditApplicant::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::active()->count();
    }
}
