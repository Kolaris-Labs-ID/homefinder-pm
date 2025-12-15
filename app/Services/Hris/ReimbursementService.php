<?php

namespace App\Services\Hris;

use App\Models\Hris\Employee;
use App\Models\Hris\Reimbursement;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ReimbursementService
{
    /**
     * Create a new reimbursement request.
     */
    public function create(Employee $employee, array $data, ?UploadedFile $receipt = null): Reimbursement
    {
        $receiptPath = null;

        if ($receipt) {
            $receiptPath = $receipt->store(
                "reimbursements/{$employee->id}",
                'private'
            );
        }

        return Reimbursement::create([
            'employee_id' => $employee->id,
            'type' => $data['type'],
            'amount' => $data['amount'],
            'description' => $data['description'] ?? null,
            'receipt_file_path' => $receiptPath,
            'status' => Reimbursement::STATUS_PENDING,
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    /**
     * Cancel a pending reimbursement.
     */
    public function cancel(Reimbursement $reimbursement): void
    {
        if (!$reimbursement->canCancel()) {
            throw new \Exception("Cannot cancel reimbursement with status: {$reimbursement->status}");
        }

        // Delete the receipt file if exists
        if ($reimbursement->receipt_file_path) {
            Storage::disk('private')->delete($reimbursement->receipt_file_path);
        }

        $reimbursement->delete();
    }

    /**
     * Approve a pending reimbursement.
     */
    public function approve(Reimbursement $reimbursement): void
    {
        if (!$reimbursement->canApprove()) {
            throw new \Exception("Cannot approve reimbursement with status: {$reimbursement->status}");
        }

        $reimbursement->update([
            'status' => Reimbursement::STATUS_APPROVED,
        ]);
    }

    /**
     * Reject a pending reimbursement.
     */
    public function reject(Reimbursement $reimbursement, string $reason): void
    {
        if (!$reimbursement->canReject()) {
            throw new \Exception("Cannot reject reimbursement with status: {$reimbursement->status}");
        }

        $reimbursement->update([
            'status' => Reimbursement::STATUS_REJECTED,
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Mark an approved reimbursement as paid.
     */
    public function markAsPaid(Reimbursement $reimbursement, User $paidBy): void
    {
        if (!$reimbursement->canMarkAsPaid()) {
            throw new \Exception("Cannot mark as paid reimbursement with status: {$reimbursement->status}");
        }

        $reimbursement->update([
            'status' => Reimbursement::STATUS_PAID,
            'paid_at' => now(),
            'paid_by' => $paidBy->id,
        ]);
    }

    /**
     * Get reimbursements for an employee.
     */
    public function getEmployeeReimbursements(Employee $employee): \Illuminate\Database\Eloquent\Collection
    {
        return Reimbursement::forEmployee($employee->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get pending reimbursements for approval.
     */
    public function getPendingReimbursements(): \Illuminate\Database\Eloquent\Collection
    {
        return Reimbursement::pending()
            ->with('employee')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Get approved reimbursements awaiting payment.
     */
    public function getApprovedReimbursements(): \Illuminate\Database\Eloquent\Collection
    {
        return Reimbursement::approved()
            ->with('employee')
            ->orderBy('created_at', 'asc')
            ->get();
    }
}
