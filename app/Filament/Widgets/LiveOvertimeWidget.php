<?php

namespace App\Filament\Widgets;

use App\Models\Hris\OvertimeLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LiveOvertimeWidget extends BaseWidget
{
    protected static ?string $heading = 'Live Overtime Sessions';

    protected static ?string $pollingInterval = '30s';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 10;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OvertimeLog::running()
                    ->with(['user:id,name', 'manager:id,name'])
                    ->orderBy('start_time', 'asc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Employee')
                    ->searchable(),
                Tables\Columns\TextColumn::make('manager.name')
                    ->label('Manager'),
                Tables\Columns\TextColumn::make('task_name')
                    ->limit(40),
                Tables\Columns\TextColumn::make('client_name')
                    ->label('Client')
                    ->default('—'),
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Started')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('running_duration')
                    ->label('Running For')
                    ->getStateUsing(function (OvertimeLog $record): string {
                        $minutes = (int) $record->start_time->diffInMinutes(now());
                        $hours   = intdiv($minutes, 60);
                        $mins    = $minutes % 60;
                        return "{$hours}h {$mins}m";
                    }),
                Tables\Columns\IconColumn::make('has_client')
                    ->label('Client Job')
                    ->getStateUsing(fn (OvertimeLog $record): bool => $record->hasClient())
                    ->boolean()
                    ->trueIcon('heroicon-o-building-office')
                    ->falseIcon('heroicon-o-minus'),
            ])
            ->emptyStateHeading('No live overtime sessions')
            ->emptyStateDescription('All employees have completed or not started overtime.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated(false);
    }
}
