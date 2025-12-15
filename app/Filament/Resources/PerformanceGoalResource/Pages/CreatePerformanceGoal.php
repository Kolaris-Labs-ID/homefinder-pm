<?php

namespace App\Filament\Resources\PerformanceGoalResource\Pages;

use App\Filament\Resources\PerformanceGoalResource;
use App\Services\Hris\PerformanceService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePerformanceGoal extends CreateRecord
{
    protected static string $resource = PerformanceGoalResource::class;

    protected function beforeCreate(): void
    {
        $service = app(PerformanceService::class);

        $isValid = $service->validateGoalWeight(
            (int) $this->data['employee_id'],
            (int) $this->data['cycle_id'],
            (float) $this->data['weight']
        );

        if (!$isValid) {
            $currentWeight = $service->getTotalGoalWeight(
                (int) $this->data['employee_id'],
                (int) $this->data['cycle_id']
            );
            $available = 100 - $currentWeight;

            Notification::make()
                ->title('Weight validation failed')
                ->body("Total weight would exceed 100%. Current: {$currentWeight}%, Available: {$available}%")
                ->danger()
                ->send();

            $this->halt();
        }
    }
}
