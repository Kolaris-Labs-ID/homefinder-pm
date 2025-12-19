<?php

namespace App\Traits\Hris;

use App\Models\Hris\ApprovalStep;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * Trait for models that require approval workflow.
 *
 * This trait provides common approval functionality for models like
 * LeaveRequest, Reimbursement, and PayrollRecord.
 *
 * Models using this trait should:
 * 1. Implement the ApprovableContract interface
 * 2. Define the getApprovalModule() method to return the module type
 * 3. Have status, approved_at, rejected_at, and rejection_reason fields
 */
trait Approvable
{
    /**
     * Get the approval steps for this approvable item.
     * This is a polymorphic relationship to ApprovalStep.
     */
    public function approvalSteps(): MorphMany
    {
        return $this->morphMany(ApprovalStep::class, 'approvable')->orderBy('step_order');
    }

    /**
     * Get the current pending approval step.
     */
    public function currentApprovalStep(): ?ApprovalStep
    {
        return $this->approvalSteps()
            ->where('status', ApprovalStep::STATUS_PENDING)
            ->first();
    }

    /**
     * Check if this item has approval workflow steps.
     */
    public function hasApprovalWorkflow(): bool
    {
        return $this->approvalSteps()->exists();
    }

    /**
     * Check if the item is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === static::STATUS_APPROVED;
    }

    /**
     * Check if the item is pending approval.
     */
    public function isPending(): bool
    {
        return $this->status === static::STATUS_PENDING;
    }

    /**
     * Check if the item is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === static::STATUS_REJECTED;
    }

    /**
     * Get the approval history for this item.
     * Returns all approval steps with their status, approver, and timestamps.
     */
    public function getApprovalHistory(): Collection
    {
        return $this->approvalSteps()
            ->with('approver')
            ->orderBy('step_order')
            ->get()
            ->map(function (ApprovalStep $step) {
                return [
                    'step_order' => $step->step_order,
                    'status' => $step->status,
                    'approver_id' => $step->approver_id,
                    'approver_name' => $step->approver?->name,
                    'approver_role' => $step->approver_role,
                    'approved_at' => $step->approved_at,
                    'notes' => $step->notes,
                ];
            });
    }

    /**
     * Mark the item as approved.
     * Called by ApprovalWorkflowService when final step is approved.
     */
    public function markAsApproved(): void
    {
        $this->update([
            'status' => static::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    /**
     * Mark the item as rejected.
     * Called by ApprovalWorkflowService when a step is rejected.
     */
    public function markAsRejected(string $reason): void
    {
        $this->update([
            'status' => static::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Check if the given user can approve this item.
     */
    public function canBeApprovedBy($user): bool
    {
        $currentStep = $this->currentApprovalStep();

        if (!$currentStep) {
            return false;
        }

        // Check if user is the designated approver
        if ($currentStep->approver_id === $user->id) {
            return true;
        }

        // Check if user has the required role
        if ($currentStep->approver_role && $user->hasRole($currentStep->approver_role)) {
            return true;
        }

        return false;
    }

    /**
     * Get the workflow snapshot stored when approval was initialized.
     * This ensures in-progress approvals use original configuration.
     */
    public function getWorkflowSnapshot(): ?array
    {
        return $this->metadata['workflow_snapshot'] ?? null;
    }

    /**
     * Store a workflow snapshot for this approvable.
     */
    public function setWorkflowSnapshot(array $snapshot): void
    {
        $metadata = $this->metadata ?? [];
        $metadata['workflow_snapshot'] = $snapshot;
        $this->update(['metadata' => $metadata]);
    }
}
