<?php

namespace App\Filament\Resources\WeeklyAllowanceBatchResource\Pages;

use App\Filament\Resources\WeeklyAllowanceBatchResource;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Services\Hris\WeeklyAllowanceService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateWeeklyAllowanceBatch extends CreateRecord
{
    protected static string $resource = WeeklyAllowanceBatchResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = WeeklyAllowanceBatch::STATUS_DRAFT;
        $data['generated_by'] = auth()->id();
        $data['generated_at'] = now();

        return $data;
    }

    protected function afterCreate(): void
    {
        $batch = $this->record;
        $service = app(WeeklyAllowanceService::class);

        // Get eligible employees and create payments
        $employees = $service->getEligibleEmployees($batch->week_end_date);

        $totalAmount = 0;
        $warningCount = 0;

        foreach ($employees as $employee) {
            $payment = $service->calculateEmployeePayment(
                $employee,
                $batch->week_start_date,
                $batch->week_end_date
            );

            if ($payment) {
                \App\Models\Hris\WeeklyAllowancePayment::create([
                    'weekly_allowance_batch_id' => $batch->id,
                    'employee_id' => $employee->id,
                    'daily_rate' => $payment['daily_rate'],
                    'days_present' => $payment['days_present'],
                    'days_breakdown' => $payment['days_breakdown'],
                    'total_amount' => $payment['total_amount'],
                    'bank_name' => $employee->bank_name,
                    'bank_account_number' => $employee->bank_account_number,
                    'bank_account_holder' => $employee->bank_account_holder,
                    'has_bank_warning' => $payment['has_bank_warning'],
                    'status' => \App\Models\Hris\WeeklyAllowancePayment::STATUS_PENDING,
                ]);

                $totalAmount += $payment['total_amount'];
                if ($payment['has_bank_warning']) {
                    $warningCount++;
                }
            }
        }

        // Update batch summary
        $batch->update([
            'total_employees' => $batch->payments()->count(),
            'employees_with_warnings' => $warningCount,
            'total_amount' => $totalAmount,
        ]);

        Notification::make()
            ->title('Batch Created')
            ->body("Added {$batch->total_employees} employees. Total: Rp " . number_format($totalAmount, 0, ',', '.'))
            ->success()
            ->send();
    }
}
