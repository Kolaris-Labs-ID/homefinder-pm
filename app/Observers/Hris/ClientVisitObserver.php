<?php

namespace App\Observers\Hris;

use App\Models\Hris\AttendanceLog;
use App\Models\Hris\ClientVisit;
use Illuminate\Support\Facades\Log;

class ClientVisitObserver
{
    /**
     * Handle the ClientVisit "creating" event.
     *
     * Automatically classifies the visit as 'sudden' if visit_date is today,
     * or 'planned' if it is in the future.
     *
     * Requirements: 2.1, 2.2, 2.3
     */
    public function creating(ClientVisit $visit): void
    {
        $visitDate = \Carbon\Carbon::parse($visit->visit_date)->startOfDay();
        $today = today()->startOfDay();

        $visit->visit_type = $visitDate->equalTo($today)
            ? ClientVisit::TYPE_SUDDEN
            : ClientVisit::TYPE_PLANNED;
    }

    /**
     * Handle the ClientVisit "created" event.
     *
     * When a sudden (same-day) visit is created, updates or creates the
     * employee's AttendanceLog for today with status='visit'.
     *
     * Requirements: 4.1, 4.2, 4.3, 4.4, 4.5
     */
    public function created(ClientVisit $visit): void
    {
        if ($visit->visit_type !== ClientVisit::TYPE_SUDDEN) {
            return;
        }

        try {
            $existing = AttendanceLog::forEmployee($visit->employee_id)
                ->forDate(today())
                ->first();

            if ($existing) {
                $existing->update([
                    'status' => AttendanceLog::STATUS_VISIT,
                    'source' => AttendanceLog::SOURCE_VISIT,
                    'client_visit_id' => $visit->id,
                ]);
            } else {
                AttendanceLog::create([
                    'employee_id' => $visit->employee_id,
                    'client_visit_id' => $visit->id,
                    'date' => today(),
                    'status' => AttendanceLog::STATUS_VISIT,
                    'source' => AttendanceLog::SOURCE_VISIT,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('ClientVisitObserver: failed to update attendance log', [
                'client_visit_id' => $visit->id,
                'employee_id' => $visit->employee_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
