<?php

namespace App\Filament\Resources;

use App\Exports\RecruitmentReportExport;
use App\Filament\Resources\JobPostingResource\Pages;
use App\Filament\Resources\JobPostingResource\RelationManagers;
use App\Models\Hris\JobPosting;
use App\Services\Hris\RecruitmentService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Maatwebsite\Excel\Facades\Excel;

class JobPostingResource extends Resource
{
    protected static ?string $model = JobPosting::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationLabel = 'Job Postings';

    protected static ?string $navigationGroup = 'Recruitment';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Job Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('department_id')
                            ->label('Department')
                            ->relationship('department', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('location')
                            ->maxLength(255),
                        Forms\Components\Select::make('employment_type')
                            ->options([
                                JobPosting::EMPLOYMENT_TYPE_FULL_TIME => 'Full Time',
                                JobPosting::EMPLOYMENT_TYPE_PART_TIME => 'Part Time',
                                JobPosting::EMPLOYMENT_TYPE_CONTRACT => 'Contract',
                                JobPosting::EMPLOYMENT_TYPE_INTERN => 'Intern',
                            ])
                            ->required(),
                    ]),

                Forms\Components\Section::make('Description & Requirements')
                    ->schema([
                        Forms\Components\RichEditor::make('description')
                            ->columnSpanFull(),
                        Forms\Components\RichEditor::make('requirements')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Public Portal')
                    ->description('Content and settings for the public job board.')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        Forms\Components\Select::make('hiring_company_id')
                            ->label('Hiring Company')
                            ->relationship('hiringCompany', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Required before publishing to the public portal.'),
                        Forms\Components\Select::make('work_arrangement')
                            ->options([
                                JobPosting::WORK_ARRANGEMENT_ONSITE => 'On-site',
                                JobPosting::WORK_ARRANGEMENT_HYBRID => 'Hybrid',
                                JobPosting::WORK_ARRANGEMENT_REMOTE => 'Remote',
                            ]),
                        Forms\Components\TextInput::make('public_title')
                            ->maxLength(255)
                            ->helperText('Overrides the internal title on the public portal.')
                            ->columnSpanFull(),
                        Forms\Components\RichEditor::make('public_description')
                            ->columnSpanFull(),
                        Forms\Components\RichEditor::make('public_requirements')
                            ->columnSpanFull(),
                        Forms\Components\RichEditor::make('public_benefits')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('salary_min')
                            ->numeric()
                            ->prefix('Rp'),
                        Forms\Components\TextInput::make('salary_max')
                            ->numeric()
                            ->prefix('Rp'),
                        Forms\Components\Toggle::make('salary_hidden')
                            ->label('Hide salary on public portal')
                            ->default(true),
                        Forms\Components\DatePicker::make('application_deadline')
                            ->helperText('Job hides from the portal after this date (Asia/Jakarta).'),
                    ]),

                Forms\Components\Section::make('Status')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                JobPosting::STATUS_DRAFT => 'Draft',
                                JobPosting::STATUS_PUBLISHED => 'Published',
                                JobPosting::STATUS_CLOSED => 'Closed',
                            ])
                            ->default(JobPosting::STATUS_DRAFT)
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\DateTimePicker::make('published_at')
                            ->label('Published At')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('closed_at')
                            ->label('Closed At')
                            ->disabled(),
                    ])
                    ->columns(3)
                    ->visibleOn('edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Department')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => JobPosting::STATUS_DRAFT,
                        'success' => JobPosting::STATUS_PUBLISHED,
                        'danger' => JobPosting::STATUS_CLOSED,
                    ]),
                Tables\Columns\IconColumn::make('is_public')
                    ->label('Public')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('hiringCompany.name')
                    ->label('Company')
                    ->toggleable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('applicants_count')
                    ->label('Applicants')
                    ->counts('applicants')
                    ->sortable(),
                Tables\Columns\TextColumn::make('employment_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        JobPosting::EMPLOYMENT_TYPE_FULL_TIME => 'Full Time',
                        JobPosting::EMPLOYMENT_TYPE_PART_TIME => 'Part Time',
                        JobPosting::EMPLOYMENT_TYPE_CONTRACT => 'Contract',
                        JobPosting::EMPLOYMENT_TYPE_INTERN => 'Intern',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('closed_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        JobPosting::STATUS_DRAFT => 'Draft',
                        JobPosting::STATUS_PUBLISHED => 'Published',
                        JobPosting::STATUS_CLOSED => 'Closed',
                    ]),
                Tables\Filters\SelectFilter::make('department')
                    ->relationship('department', 'name'),
                Tables\Filters\SelectFilter::make('employment_type')
                    ->options([
                        JobPosting::EMPLOYMENT_TYPE_FULL_TIME => 'Full Time',
                        JobPosting::EMPLOYMENT_TYPE_PART_TIME => 'Part Time',
                        JobPosting::EMPLOYMENT_TYPE_CONTRACT => 'Contract',
                        JobPosting::EMPLOYMENT_TYPE_INTERN => 'Intern',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-globe-alt')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Publish Job Posting')
                    ->modalDescription('Are you sure you want to publish this job posting? It will become visible to applicants.')
                    ->visible(fn (JobPosting $record): bool => $record->isDraft())
                    ->action(function (JobPosting $record): void {
                        $service = app(RecruitmentService::class);
                        $service->publishJobPosting($record);

                        Notification::make()
                            ->title('Job posting published')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('close')
                    ->label('Close')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Close Job Posting')
                    ->modalDescription('Are you sure you want to close this job posting? It will no longer accept new applicants.')
                    ->visible(fn (JobPosting $record): bool => $record->isPublished())
                    ->action(function (JobPosting $record): void {
                        $service = app(RecruitmentService::class);
                        $service->closeJobPosting($record);

                        Notification::make()
                            ->title('Job posting closed')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('publishPublic')
                    ->label('Publish to Portal')
                    ->icon('heroicon-o-megaphone')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Publish to Public Portal')
                    ->modalDescription('Make this job visible on the public job board.')
                    ->visible(fn (JobPosting $record): bool => $record->isPublished() && ! $record->is_public)
                    ->action(function (JobPosting $record): void {
                        try {
                            app(RecruitmentService::class)->publishJobPostingPublicly($record);
                            Notification::make()->title('Published to public portal')->success()->send();
                        } catch (\Throwable $e) {
                            Notification::make()->title('Cannot publish')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('unpublishPublic')
                    ->label('Remove from Portal')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (JobPosting $record): bool => (bool) $record->is_public)
                    ->action(function (JobPosting $record): void {
                        app(RecruitmentService::class)->unpublishJobPostingPublicly($record);
                        Notification::make()->title('Removed from public portal')->success()->send();
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('exportRecruitment')
                    ->label('Export Recruitment Report')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->form([
                        Forms\Components\DatePicker::make('start_date')
                            ->label('Start Date')
                            ->default(now()->startOfYear())
                            ->required(),
                        Forms\Components\DatePicker::make('end_date')
                            ->label('End Date')
                            ->default(now())
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $start = Carbon::parse($data['start_date']);
                        $end = Carbon::parse($data['end_date']);

                        return Excel::download(
                            new RecruitmentReportExport($start, $end),
                            'recruitment-report-'.now()->format('Y-m-d').'.xlsx'
                        );
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ScreeningCriteriaRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJobPostings::route('/'),
            'create' => Pages\CreateJobPosting::route('/create'),
            'view' => Pages\ViewJobPosting::route('/{record}'),
            'edit' => Pages\EditJobPosting::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::published()->count();
    }
}
