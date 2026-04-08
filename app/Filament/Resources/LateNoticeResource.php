<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LateNoticeResource\Pages;
use App\Models\Hris\LateNotice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LateNoticeResource extends Resource
{
    protected static ?string $model = LateNotice::class;

    protected static ?string $navigationGroup = 'Attendance';

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Late Reports';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Late Report Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('employee.user.name')
                            ->label('Employee')
                            ->disabled(),

                        Forms\Components\DatePicker::make('date')
                            ->disabled(),

                        Forms\Components\TextInput::make('minutes_late')
                            ->label('Minutes Late')
                            ->suffix('min')
                            ->disabled(),

                        Forms\Components\TextInput::make('penalty_percent')
                            ->label('Penalty Tier')
                            ->suffix('%')
                            ->disabled(),

                        Forms\Components\Select::make('status')
                            ->options([
                                LateNotice::STATUS_PENDING  => 'Pending',
                                LateNotice::STATUS_APPROVED => 'Pardoned',
                                LateNotice::STATUS_REJECTED => 'Rejected',
                            ])
                            ->disabled(),

                        Forms\Components\Textarea::make('reason')
                            ->label('Reason (filled by HR after discussion)')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Note')
                            ->columnSpanFull()
                            ->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('employee.user.name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('minutes_late')
                    ->label('Late (min)')
                    ->suffix(' min')
                    ->sortable(),

                Tables\Columns\TextColumn::make('penalty_percent')
                    ->label('Penalty')
                    ->suffix('%')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state >= 100 => 'danger',
                        $state >= 50  => 'warning',
                        default       => 'gray',
                    }),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Reason')
                    ->placeholder('—')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->reason),

                Tables\Columns\BadgeColumn::make('status')
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        LateNotice::STATUS_APPROVED => 'Pardoned',
                        LateNotice::STATUS_REJECTED => 'Rejected',
                        default                     => 'Pending',
                    })
                    ->colors([
                        'warning' => LateNotice::STATUS_PENDING,
                        'success' => LateNotice::STATUS_APPROVED,
                        'danger'  => LateNotice::STATUS_REJECTED,
                    ]),

                Tables\Columns\TextColumn::make('reviewer.name')
                    ->label('Reviewed By')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('reviewed_at')
                    ->label('Reviewed At')
                    ->dateTime('d M Y H:i')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Reported At')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        LateNotice::STATUS_PENDING  => 'Pending',
                        LateNotice::STATUS_APPROVED => 'Pardoned',
                        LateNotice::STATUS_REJECTED => 'Rejected',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('pardon')
                    ->label('Pardon')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (LateNotice $record) => $record->isPending())
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason (from employee discussion)')
                            ->required(),
                    ])
                    ->action(function (LateNotice $record, array $data) {
                        $record->update([
                            'status'      => LateNotice::STATUS_APPROVED,
                            'reason'      => $data['reason'],
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]);
                        Notification::make()
                            ->title('Late notice pardoned — no deduction applied.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (LateNotice $record) => $record->isPending())
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason (from employee discussion)')
                            ->required(),
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Note')
                            ->required(),
                    ])
                    ->action(function (LateNotice $record, array $data) {
                        $record->update([
                            'status'           => LateNotice::STATUS_REJECTED,
                            'reason'           => $data['reason'],
                            'rejection_reason' => $data['rejection_reason'],
                            'reviewed_by'      => auth()->id(),
                            'reviewed_at'      => now(),
                        ]);
                        Notification::make()
                            ->title('Late notice rejected — daily allowance deduction applies.')
                            ->warning()
                            ->send();
                    }),

                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_pardon')
                    ->label('Pardon Selected')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason (applied to all selected)')
                            ->required(),
                    ])
                    ->action(function ($records, array $data) {
                        $records->each(function (LateNotice $r) use ($data) {
                            if ($r->isPending()) {
                                $r->update([
                                    'status'      => LateNotice::STATUS_APPROVED,
                                    'reason'      => $data['reason'],
                                    'reviewed_by' => auth()->id(),
                                    'reviewed_at' => now(),
                                ]);
                            }
                        });
                        Notification::make()->title('Selected notices pardoned.')->success()->send();
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLateNotices::route('/'),
            'view'  => Pages\ViewLateNotice::route('/{record}'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_late_notices') ?? false;
    }
}
