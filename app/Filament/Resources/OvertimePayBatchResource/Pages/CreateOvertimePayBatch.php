<?php

namespace App\Filament\Resources\OvertimePayBatchResource\Pages;

use App\Filament\Resources\OvertimePayBatchResource;
use App\Models\Hris\OvertimePayBatch;
use App\Services\Hris\OvertimePayService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateOvertimePayBatch extends CreateRecord
{
    protected static string $resource = OvertimePayBatchResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $service = app(OvertimePayService::class);

        try {
            $batch = $service->generateBatch(
                \Carbon\Carbon::parse($data['week_start_date']),
                \Carbon\Carbon::parse($data['week_end_date']),
                \Carbon\Carbon::parse($data['payment_date'])
            );

            Notification::make()
                ->title("Batch generated: {$batch->total_employees} employee(s)")
                ->success()
                ->send();

            return $batch;
        } catch (\Exception $e) {
            Notification::make()
                ->title('Generation Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt();

            return new OvertimePayBatch(); // unreachable — halt() throws
        }
    }
}
