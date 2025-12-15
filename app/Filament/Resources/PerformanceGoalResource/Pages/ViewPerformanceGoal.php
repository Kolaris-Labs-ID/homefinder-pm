<?php

namespace App\Filament\Resources\PerformanceGoalResource\Pages;

use App\Filament\Resources\PerformanceGoalResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPerformanceGoal extends ViewRecord
{
    protected static string $resource = PerformanceGoalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
