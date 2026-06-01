<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FinanceAuditLogResource\Pages;
use App\Models\Hris\FinanceAuditLog;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FinanceAuditLogResource extends Resource
{
    protected static ?string $model = FinanceAuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Finance Center';

    protected static ?string $navigationLabel = 'Audit Log';

    protected static ?int $navigationSort = 9;

    public static function canViewAny(): bool
    {
        return static::financeCan('finance_center.view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Event')->schema([
                TextEntry::make('created_at')->dateTime()->label('Time'),
                TextEntry::make('event_type')->badge(),
                TextEntry::make('actor.name')->label('Actor')->placeholder('—'),
                TextEntry::make('actor_role')->badge()->placeholder('—'),
                TextEntry::make('reason')->placeholder('—')->columnSpanFull(),
                TextEntry::make('ip_address')->placeholder('—'),
                TextEntry::make('user_agent')->placeholder('—')->columnSpanFull(),
            ])->columns(2),

            Section::make('Target')->schema([
                TextEntry::make('auditable_type')->label('Type'),
                TextEntry::make('auditable_id')->label('ID'),
            ])->columns(2),

            Section::make('Integrity')->schema([
                TextEntry::make('previous_hash')->label('Previous hash')->limit(20)->copyable()->placeholder('—'),
                TextEntry::make('current_hash')->label('Current hash')->limit(20)->copyable(),
            ])->columns(2),

            Section::make('Snapshots')->schema([
                KeyValueEntry::make('before_snapshot')
                    ->label('Before')
                    ->visible(fn ($record) => filled($record->before_snapshot)),
                KeyValueEntry::make('after_snapshot')
                    ->label('After')
                    ->visible(fn ($record) => filled($record->after_snapshot)),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Time'),
                Tables\Columns\TextColumn::make('event_type')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('auditable_id')
                    ->label('Target')
                    ->state(fn ($record) => class_basename($record->auditable_type).' #'.$record->auditable_id),
                Tables\Columns\TextColumn::make('actor.name')
                    ->label('Actor')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('actor_role')
                    ->badge()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('reason')
                    ->limit(40)
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('event_type')
                    ->options(fn () => FinanceAuditLog::query()
                        ->distinct()
                        ->orderBy('event_type')
                        ->pluck('event_type', 'event_type')
                        ->all()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFinanceAuditLogs::route('/'),
            'view' => Pages\ViewFinanceAuditLog::route('/{record}'),
        ];
    }

    protected static function financeCan(string $permission): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        if ($user->hasRole('super_admin')) {
            return true;
        }
        try {
            return $user->hasPermissionTo($permission, 'web');
        } catch (\Throwable $e) {
            return false;
        }
    }
}
