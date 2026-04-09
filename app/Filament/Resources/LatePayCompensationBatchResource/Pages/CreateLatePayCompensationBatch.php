<?php

namespace App\Filament\Resources\LatePayCompensationBatchResource\Pages;

use App\Filament\Resources\LatePayCompensationBatchResource;
use App\Models\Hris\LatePayCompensationBatch;
use App\Services\Hris\LatePayCompensationService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateLatePayCompensationBatch extends CreateRecord
{
    protected static string $resource = LatePayCompensationBatchResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $service = app(LatePayCompensationService::class);

        try {
            $transfer = ! empty($data['compensation_transfer_date'])
                ? \Carbon\Carbon::parse($data['compensation_transfer_date'])
                : null;

            $batch = $service->generateBatch(
                \Carbon\Carbon::parse($data['scheduled_pay_date']),
                \Carbon\Carbon::parse($data['actual_pay_date']),
                $transfer,
                (float) ($data['rate_percent'] ?? 1),
            );

            if (! empty($data['notes'])) {
                $batch->update(['notes' => $data['notes']]);
            }

            Notification::make()
                ->title("Batch generated: {$batch->total_employees} employee(s)")
                ->success()
                ->send();

            return $batch->fresh();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Generation failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt();

            return new LatePayCompensationBatch;
        }
    }
}
