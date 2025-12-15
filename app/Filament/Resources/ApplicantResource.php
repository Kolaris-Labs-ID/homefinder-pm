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
                            ->label('Resume')
                            ->directory('applicants/resumes')
                            ->visibility('private')
                            ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                            ->maxSize(5120),
                        Forms\Components\Textarea::make('cover_letter')
                            ->rows(5)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                Applicant::STATUS_APPLIED => 'Applied',
                                Applicant::STATUS_SHORTLISTED => 'Shortlisted',
                                Applicant::STATUS_INTERVIEWED => 'Interviewed',
                                Applicant::STATUS_OFFERED => 'Offered',
                                Applicant::STATUS_HIRED => 'Hired',
                                Applicant::STATUS_REJECTED => 'Rejected',
                            ])
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
                        'gray' => Applicant::STATUS_APPLIED,
                        'info' => Applicant::STATUS_SHORTLISTED,
                        'warning' => Applicant::STATUS_INTERVIEWED,
                        'primary' => Applicant::STATUS_OFFERED,
                        'success' => Applicant::STATUS_HIRED,
                        'danger' => Applicant::STATUS_REJECTED,
                    ])
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
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
                    ->options([
                        Applicant::STATUS_APPLIED => 'Applied',
                        Applicant::STATUS_SHORTLISTED => 'Shortlisted',
                        Applicant::STATUS_INTERVIEWED => 'Interviewed',
                        Applicant::STATUS_OFFERED => 'Offered',
                        Applicant::STATUS_HIRED => 'Hired',
                        Applicant::STATUS_REJECTED => 'Rejected',
                    ]),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('advance')
                        ->label(fn (Applicant $record): string => 'Advance to ' . ucfirst($record->getNextStatus() ?? ''))
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
                                    ->title('Applicant advanced to ' . ucfirst($nextStatus))
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
                        ->visible(fn (Applicant $record): bool => $record->canAdvance())
                        ->action(function (Applicant $record, array $data): void {
                            $service = app(RecruitmentService::class);
                            $service->rejectApplicant($record, $data['rejection_reason']);
                            Notification::make()
                                ->title('Applicant rejected')
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
                        ->visible(fn (Applicant $record): bool => !$record->isHired() && !$record->isRejected())
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
