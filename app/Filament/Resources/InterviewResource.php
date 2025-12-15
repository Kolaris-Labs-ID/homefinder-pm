<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InterviewResource\Pages;
use App\Models\Hris\Interview;
use App\Services\Hris\RecruitmentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InterviewResource extends Resource
{
    protected static ?string $model = Interview::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Interviews';

    protected static ?string $navigationGroup = 'Recruitment';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Interview Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('applicant_id')
                            ->label('Applicant')
                            ->relationship('applicant', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('interviewer_id')
                            ->label('Interviewer')
                            ->relationship('interviewer', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\DateTimePicker::make('scheduled_at')
                            ->label('Scheduled At')
                            ->required(),
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
                    ]),

                Forms\Components\Section::make('Result')
                    ->schema([
                        Forms\Components\Select::make('result')
                            ->options([
                                Interview::RESULT_PENDING => 'Pending',
                                Interview::RESULT_PASS => 'Pass',
                                Interview::RESULT_FAIL => 'Fail',
                            ])
                            ->default(Interview::RESULT_PENDING)
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\Textarea::make('notes')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->visibleOn('edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('applicant.name')
                    ->label('Applicant')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('applicant.jobPosting.title')
                    ->label('Job Posting')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('interviewer.name')
                    ->label('Interviewer')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('scheduled_at')
                    ->label('Scheduled Time')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('interview_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                Tables\Columns\TextColumn::make('result')
                    ->badge()
                    ->colors([
                        'gray' => Interview::RESULT_PENDING,
                        'success' => Interview::RESULT_PASS,
                        'danger' => Interview::RESULT_FAIL,
                    ])
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                Tables\Columns\TextColumn::make('location')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('result')
                    ->options([
                        Interview::RESULT_PENDING => 'Pending',
                        Interview::RESULT_PASS => 'Pass',
                        Interview::RESULT_FAIL => 'Fail',
                    ]),
                Tables\Filters\SelectFilter::make('interview_type')
                    ->label('Type')
                    ->options([
                        Interview::TYPE_PHONE => 'Phone',
                        Interview::TYPE_VIDEO => 'Video',
                        Interview::TYPE_ONSITE => 'Onsite',
                    ]),
                Tables\Filters\SelectFilter::make('interviewer')
                    ->relationship('interviewer', 'name'),
            ])
            ->actions([
                Tables\Actions\Action::make('record_result')
                    ->label('Record Result')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('result')
                            ->options([
                                Interview::RESULT_PASS => 'Pass',
                                Interview::RESULT_FAIL => 'Fail',
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(4),
                    ])
                    ->visible(fn (Interview $record): bool => $record->isPending())
                    ->action(function (Interview $record, array $data): void {
                        $service = app(RecruitmentService::class);
                        $service->recordInterviewResult($record, $data['result'], $data['notes'] ?? null);

                        Notification::make()
                            ->title('Interview result recorded')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('scheduled_at', 'desc');
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
            'index' => Pages\ListInterviews::route('/'),
            'create' => Pages\CreateInterview::route('/create'),
            'view' => Pages\ViewInterview::route('/{record}'),
            'edit' => Pages\EditInterview::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::pending()->count();
    }
}
