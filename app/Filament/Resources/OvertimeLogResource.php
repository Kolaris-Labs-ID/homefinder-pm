<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OvertimeLogResource\Pages;
use App\Filament\Resources\OvertimeLogResource\Pages\CreateOvertimeLog;
use App\Filament\Resources\OvertimeLogResource\Pages\EditOvertimeLog;
use App\Filament\Resources\OvertimeLogResource\Pages\ListOvertimeLogs;
use App\Filament\Resources\OvertimeLogResource\Pages\ViewOvertimeLog;
use App\Models\Hris\OvertimeLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OvertimeLogResource extends Resource
{
    protected static ?string $model = OvertimeLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Payroll';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Overtime (Lembur)';

    protected static ?string $modelLabel = 'Overtime Log';

    protected static ?string $pluralModelLabel = 'Overtime Logs';

    // -------------------------------------------------------------------------
    // Form
    // -------------------------------------------------------------------------

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Session Info')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Employee')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('manager_id')
                            ->label('Manager / Assigner')
                            ->relationship('manager', 'name')
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make('task_name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('client_name')
                            ->label('Client (optional)')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Timing & Pay')
                    ->schema([
                        Forms\Components\DateTimePicker::make('start_time')
                            ->required(),
                        Forms\Components\DateTimePicker::make('end_time'),
                        Forms\Components\TextInput::make('duration_minutes')
                            ->numeric()
                            ->suffix('min')
                            ->minValue(0),
                        Forms\Components\TextInput::make('hourly_rate')
                            ->numeric()
                            ->prefix('Rp')
                            ->minValue(0),
                    ])->columns(2),

                Forms\Components\Section::make('Approval')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options(OvertimeLog::getStatusLabels())
                            ->required(),
                        Forms\Components\Textarea::make('rejection_note')
                            ->rows(3),
                        Forms\Components\Toggle::make('auto_closed')
                            ->label('Auto-closed by system'),
                    ])->columns(2),
            ]);
    }

    // -------------------------------------------------------------------------
    // Table
    // -------------------------------------------------------------------------

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('manager.name')
                    ->label('Manager')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('task_name')
                    ->limit(40)
                    ->searchable(),
                Tables\Columns\TextColumn::make('client_name')
                    ->label('Client')
                    ->default('—'),
                Tables\Columns\TextColumn::make('start_time')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('duration_minutes')
                    ->label('Duration')
                    ->formatStateUsing(fn (?int $state): string => $state
                        ? sprintf('%dh %dm', intdiv($state, 60), $state % 60)
                        : '—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('hourly_rate')
                    ->label('Hourly Rate')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('estimated_pay')
                    ->label('Est. Pay')
                    ->money('IDR')
                    ->getStateUsing(fn (OvertimeLog $record): float => $record->calculateEstimatedPay()),
                Tables\Columns\ImageColumn::make('evidence_path')
                    ->label('Evidence')
                    ->disk('local')
                    ->size(50)
                    ->defaultImageUrl(fn (OvertimeLog $record): string => $record->evidence_path ? '' : ''),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        OvertimeLog::STATUS_RUNNING  => 'info',
                        OvertimeLog::STATUS_PENDING  => 'warning',
                        OvertimeLog::STATUS_APPROVED => 'success',
                        OvertimeLog::STATUS_REJECTED => 'danger',
                        OvertimeLog::STATUS_FLAGGED  => 'gray',
                        default                      => 'gray',
                    }),
                Tables\Columns\IconColumn::make('auto_closed')
                    ->label('Auto-closed')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('warning'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(OvertimeLog::getStatusLabels()),
                Tables\Filters\Filter::make('auto_closed')
                    ->label('Auto-closed only')
                    ->query(fn (Builder $query) => $query->where('auto_closed', true)),
                Tables\Filters\Filter::make('has_client')
                    ->label('Has client')
                    ->query(fn (Builder $query) => $query->whereNotNull('client_name')),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (OvertimeLog $record): bool => in_array($record->status, [
                        OvertimeLog::STATUS_PENDING,
                        OvertimeLog::STATUS_FLAGGED,
                    ]))
                    ->requiresConfirmation()
                    ->modalHeading('Approve Overtime Session')
                    ->modalDescription('Are you sure you want to approve this overtime session?')
                    ->action(function (OvertimeLog $record): void {
                        $record->update([
                            'status'         => OvertimeLog::STATUS_APPROVED,
                            'rejection_note' => null,
                        ]);
                        Notification::make()
                            ->title('Overtime approved.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (OvertimeLog $record): bool => in_array($record->status, [
                        OvertimeLog::STATUS_PENDING,
                        OvertimeLog::STATUS_FLAGGED,
                        OvertimeLog::STATUS_APPROVED,
                    ]))
                    ->form([
                        Forms\Components\Textarea::make('rejection_note')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (OvertimeLog $record, array $data): void {
                        $record->update([
                            'status'         => OvertimeLog::STATUS_REJECTED,
                            'rejection_note' => $data['rejection_note'],
                        ]);
                        Notification::make()
                            ->title('Overtime rejected.')
                            ->danger()
                            ->send();
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_approve')
                    ->label('Approve Selected')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                        $count = $records
                            ->filter(fn (OvertimeLog $r) => in_array($r->status, [
                                OvertimeLog::STATUS_PENDING,
                                OvertimeLog::STATUS_FLAGGED,
                            ]))
                            ->each(fn (OvertimeLog $r) => $r->update([
                                'status'         => OvertimeLog::STATUS_APPROVED,
                                'rejection_note' => null,
                            ]))
                            ->count();

                        Notification::make()
                            ->title("{$count} overtime session(s) approved.")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    // -------------------------------------------------------------------------
    // Pages
    // -------------------------------------------------------------------------

    public static function getPages(): array
    {
        return [
            'index'  => ListOvertimeLogs::route('/'),
            'create' => CreateOvertimeLog::route('/create'),
            'view'   => ViewOvertimeLog::route('/{record}'),
            'edit'   => EditOvertimeLog::route('/{record}/edit'),
        ];
    }
}
