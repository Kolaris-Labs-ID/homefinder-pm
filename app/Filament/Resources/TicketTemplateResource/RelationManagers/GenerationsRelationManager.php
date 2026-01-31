<?php

namespace App\Filament\Resources\TicketTemplateResource\RelationManagers;

use App\Filament\Resources\TicketResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Relation manager for displaying ticket generation history.
 * Requirements: 7.3
 */
class GenerationsRelationManager extends RelationManager
{
    protected static string $relationship = 'generations';

    protected static ?string $title = 'Generation History';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->generations()->count();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('ticket.uuid')
                    ->label(__('Ticket ID'))
                    ->url(fn ($record) => $record->ticket
                        ? TicketResource::getUrl('view', ['record' => $record->ticket_id])
                        : null)
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('ticket.name')
                    ->label(__('Ticket Name'))
                    ->limit(40)
                    ->searchable(),

                Tables\Columns\TextColumn::make('target_date')
                    ->label(__('Target Date'))
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('generated_at')
                    ->label(__('Generated At'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ticket.status.name')
                    ->label(__('Current Status'))
                    ->badge()
                    ->color(fn ($record) => match ($record->ticket?->status?->name) {
                        'To Do' => 'warning',
                        'In Progress' => 'info',
                        'Review' => 'primary',
                        'Done' => 'success',
                        default => 'gray',
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('view_ticket')
                    ->label(__('View Ticket'))
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => $record->ticket
                        ? TicketResource::getUrl('view', ['record' => $record->ticket_id])
                        : null)
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record->ticket !== null),
            ])
            ->bulkActions([
                //
            ])
            ->defaultSort('generated_at', 'desc');
    }
}
