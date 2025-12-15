<?php

namespace App\Observers\Hris;

use App\Models\Hris\PayrunBatch;
use App\Models\Hris\Reimbursement;
use App\Models\Hris\SalaryStructure;
use Illuminate\Support\Facades\Log;

class PayrollAuditObserver
{
    /**
     * Handle the SalaryStructure "created" event.
     */
    public function salaryStructureCreated(SalaryStructure $salaryStructure): void
    {
        Log::channel('audit')->info('Salary structure created', [
            'salary_structure_id' => $salaryStructure->id,
            'employee_id' => $salaryStructure->employee_id,
            'effective_date' => $salaryStructure->effective_date->toDateString(),
            'basic_salary' => $salaryStructure->basic_salary,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Handle the SalaryStructure "updated" event.
     */
    public function salaryStructureUpdated(SalaryStructure $salaryStructure): void
    {
        Log::channel('audit')->info('Salary structure updated', [
            'salary_structure_id' => $salaryStructure->id,
            'employee_id' => $salaryStructure->employee_id,
            'changes' => $salaryStructure->getChanges(),
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Handle the PayrunBatch "updated" event for status transitions.
     */
    public function payrunBatchUpdated(PayrunBatch $batch): void
    {
        if ($batch->wasChanged('status')) {
            $oldStatus = $batch->getOriginal('status');
            $newStatus = $batch->status;

            Log::channel('audit')->info('Payrun batch status changed', [
                'payrun_batch_id' => $batch->id,
                'period' => $batch->period_label,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'user_id' => auth()->id(),
            ]);
        }
    }

    /**
     * Handle the Reimbursement "updated" event for status transitions.
     */
    public function reimbursementUpdated(Reimbursement $reimbursement): void
    {
        if ($reimbursement->wasChanged('status')) {
            $oldStatus = $reimbursement->getOriginal('status');
            $newStatus = $reimbursement->status;

            $logData = [
                'reimbursement_id' => $reimbursement->id,
                'employee_id' => $reimbursement->employee_id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'user_id' => auth()->id(),
            ];

            if ($newStatus === 'rejected') {
                $logData['rejection_reason'] = $reimbursement->rejection_reason;
            }

            Log::channel('audit')->info('Reimbursement status changed', $logData);
        }
    }
}
