<?php

namespace App\Filament\Resources\WeeklyAllowanceBatchResource\Pages;

use App\Filament\Resources\WeeklyAllowanceBatchResource;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Services\Hris\WeeklyAllowanceService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListWeeklyAllowanceBatches extends ListRecords
{
    protected static string $resource = WeeklyAllowanceBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate')
                ->label('Generate Weekly Batch')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Generate Weekly Allowance Batch')
                ->modalDescription(function () {
                    $dates = WeeklyAllowanceService::getPreviousWeekDates();
                    return "This will generate a batch for {$dates['start']->format('d M')} - {$dates['end']->format('d M Y')} (previous week). Payment date: {$dates['payment']->format('d M Y')}.";
                })
                ->action(function () {
                    try {
                        $dates = WeeklyAllowanceService::getPreviousWeekDates();
                        $service = app(WeeklyAllowanceService::class);
                        $batch = $service->generateBatch(
                            $dates['start'],
                            $dates['end'],
                            $dates['payment'],
                            auth()->user()
                        );

                        Notification::make()
                            ->title('Batch Generated')
                            ->body("Created batch with {$batch->total_employees} employees. Total: Rp " . number_format($batch->total_amount, 0, ',', '.'))
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Generation Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\CreateAction::make()
                ->label('Custom Batch'),
        ];
    }
}
