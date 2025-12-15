<?php

namespace App\Filament\Resources\PerformanceReviewResource\Pages;

use App\Filament\Resources\PerformanceReviewResource;
use App\Models\Hris\Employee;
use App\Models\Hris\PerformanceCycle;
use App\Services\Hris\PerformanceService;
use Filament\Resources\Pages\CreateRecord;

class CreatePerformanceReview extends CreateRecord
{
    protected static string $resource = PerformanceReviewResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // If overall_score is not provided, calculate it from goals
        if (empty($data['overall_score'])) {
            $employee = Employee::find($data['employee_id']);
            $cycle = PerformanceCycle::find($data['cycle_id']);

            if ($employee && $cycle) {
                $service = app(PerformanceService::class);
                $data['overall_score'] = $service->calculateOverallScore($employee, $cycle);
            }
        }

        return $data;
    }
}
