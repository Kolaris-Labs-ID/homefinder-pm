<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrainingRecordResource\Pages;
use App\Models\Hris\TrainingRecord;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TrainingRecordResource extends Resource
{
    protected static ?string $model = TrainingRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Training Records';

    protected static ?string $navigationGroup = 'Training';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        $user = auth()->user();
        $employee = $user?->employee;
        $canManageOthers = $user?->can('manage_training_record') || $user?->hasRole(['super_admin', 'hr_manager']);

        return $form
            ->schema([
                Forms\Components\Section::make('Training Information')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('employee_id')
                            ->label('Employee')
                            ->relationship('employee', 'id')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->user?->name ?? "Employee #{$record->id}")
                            ->searchable(['user.name'])
                            ->preload()
                            ->required()
                            ->default(fn () => $employee?->id)
                            ->disabled(fn () => !$canManageOthers)
                            ->dehydrated()
                            ->helperText(fn () => !$canManageOthers ? 'Training record will be created for yourself' : null),
                        Forms\Components\Select::make('status')
                            ->options([
                                TrainingRecord::STATUS_PLANNED => 'Planned',
                                TrainingRecord::STATUS_IN_PROGRESS => 'In Progress',
                                TrainingRecord::STATUS_COMPLETED => 'Completed',
                            ])
                            ->default(TrainingRecord::STATUS_PLANNED)
                            ->required(),
                        Forms\Components\Select::make('training_type')
                            ->label('Training Type')
                            ->options([
                                TrainingRecord::TRAINING_TYPE_FREE => 'Free',
                                TrainingRecord::TRAINING_TYPE_PAID => 'Paid (Reimbursable)',
                            ])
                            ->default(TrainingRecord::TRAINING_TYPE_FREE)
                            ->required()
                            ->helperText('Select "Paid" if you paid for this training yourself and want to request reimbursement'),
                        Forms\Components\TextInput::make('training_name')
                            ->label('Training Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('provider')
                            ->label('Provider')
                            ->maxLength(255),
                    ]),

                Forms\Components\Section::make('Details')
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Dates')
                    ->columns(2)
                    ->schema([
                        Forms\Components\DatePicker::make('completion_date')
                            ->label('Completion Date'),
                        Forms\Components\DatePicker::make('expiry_date')
                            ->label('Expiry Date')
                            ->helperText('Leave empty if certification does not expire'),
                    ]),

                Forms\Components\Section::make('Certificate')
                    ->schema([
                        Forms\Components\FileUpload::make('certificate_file_path')
                            ->label('Certificate File')
                            ->disk('private')
                            ->directory('training-certificates')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->maxSize(5120)
                            ->helperText('Upload certificate (PDF or image, max 5MB)'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.user.name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('training_name')
                    ->label('Training')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('provider')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => TrainingRecord::STATUS_PLANNED,
                        'info' => TrainingRecord::STATUS_IN_PROGRESS,
                        'success' => TrainingRecord::STATUS_COMPLETED,
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        TrainingRecord::STATUS_PLANNED => 'Planned',
                        TrainingRecord::STATUS_IN_PROGRESS => 'In Progress',
                        TrainingRecord::STATUS_COMPLETED => 'Completed',
                        default => $state,
                    }),
                Tables\Columns\BadgeColumn::make('training_type')
                    ->label('Type')
                    ->colors([
                        'info' => TrainingRecord::TRAINING_TYPE_FREE,
                        'warning' => TrainingRecord::TRAINING_TYPE_PAID,
                    ])
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        TrainingRecord::TRAINING_TYPE_FREE => 'Free',
                        TrainingRecord::TRAINING_TYPE_PAID => 'Paid',
                        default => '-',
                    }),
                Tables\Columns\TextColumn::make('pgp_points')
                    ->label('PGP')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('approved_at')
                    ->label('PGP Approved')
                    ->dateTime('M d, Y')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('completion_date')
                    ->label('Completed')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expiry_date')
                    ->label('Expires')
                    ->date()
                    ->sortable()
                    ->placeholder('No expiry')
                    ->color(fn (?TrainingRecord $record): ?string =>
                        $record?->isExpired() ? 'danger' :
                        ($record?->isExpiringWithinDays(30) ? 'warning' : null)
                    ),
                Tables\Columns\IconColumn::make('expiry_status')
                    ->label('Status')
                    ->state(fn (TrainingRecord $record): string =>
                        $record->isExpired() ? 'expired' :
                        ($record->isExpiringWithinDays(30) ? 'expiring' : 'valid')
                    )
                    ->icon(fn (string $state): string => match ($state) {
                        'expired' => 'heroicon-o-x-circle',
                        'expiring' => 'heroicon-o-exclamation-triangle',
                        'valid' => 'heroicon-o-check-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'expired' => 'danger',
                        'expiring' => 'warning',
                        'valid' => 'success',
                    })
                    ->tooltip(fn (TrainingRecord $record): string =>
                        $record->isExpired() ? 'Certification expired' :
                        ($record->isExpiringWithinDays(30) ?
                            "Expiring in {$record->days_until_expiry} days" :
                            ($record->expiry_date ? 'Valid' : 'No expiry'))
                    ),
                Tables\Columns\IconColumn::make('certificate_file_path')
                    ->label('Certificate')
                    ->boolean()
                    ->trueIcon('heroicon-o-document')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('success')
                    ->falseColor('gray'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('employee')
                    ->relationship('employee', 'id')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->user?->name ?? "Employee #{$record->id}")
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        TrainingRecord::STATUS_PLANNED => 'Planned',
                        TrainingRecord::STATUS_IN_PROGRESS => 'In Progress',
                        TrainingRecord::STATUS_COMPLETED => 'Completed',
                    ]),
                Tables\Filters\SelectFilter::make('training_type')
                    ->label('Training Type')
                    ->options([
                        TrainingRecord::TRAINING_TYPE_FREE => 'Free',
                        TrainingRecord::TRAINING_TYPE_PAID => 'Paid (Reimbursable)',
                    ]),
                Tables\Filters\Filter::make('with_pgp')
                    ->label('Has PGP Points')
                    ->query(fn ($query) => $query->withPgpPoints()),
                Tables\Filters\Filter::make('expiring_soon')
                    ->label('Expiring within 30 days')
                    ->query(fn ($query) => $query->expiringWithinDays(30)),
                Tables\Filters\Filter::make('expired')
                    ->label('Expired')
                    ->query(fn ($query) => $query->expired()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordClasses(fn (TrainingRecord $record): string =>
                $record->isExpired() ? 'bg-danger-50 dark:bg-danger-950' :
                ($record->isExpiringWithinDays(30) ? 'bg-warning-50 dark:bg-warning-950' : '')
            );
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
            'index' => Pages\ListTrainingRecords::route('/'),
            'create' => Pages\CreateTrainingRecord::route('/create'),
            'view' => Pages\ViewTrainingRecord::route('/{record}'),
            'edit' => Pages\EditTrainingRecord::route('/{record}/edit'),
        ];
    }
}
