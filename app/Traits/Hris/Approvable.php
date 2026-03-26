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
     * Get the current pending approval step (first pending step overall).
     */
    public function currentApprovalStep(): ?ApprovalStep
    {
        return $this->approvalSteps()
            ->where('status', ApprovalStep::STATUS_PENDING)
            ->first();
    }

    /**
     * Get the pending approval step assigned to a specific user.
     * Supports both direct user assignment and role-based assignment.
     * Used by parallel workflows where each assignee has their own pending step.
     */
    public function pendingStepFor($user): ?ApprovalStep
    {
        $userRoles = $user->roles->pluck('name')->toArray();

        return $this->approvalSteps()
            ->where('status', ApprovalStep::STATUS_PENDING)
            ->where(function ($query) use ($user, $userRoles) {
                $query->where('approver_id', $user->id);
                if (!empty($userRoles)) {
                    $query->orWhere(function ($q) use ($user, $userRoles) {
                        $q->whereIn('approver_role', $userRoles)
                            ->whereNull('approver_id');
                    });
                }
            })
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
     * For parallel workflows each assignee has their own pending step, so this
     * checks whether the user has any pending step assigned to them (by id or role).
     */
    public function canBeApprovedBy($user): bool
    {
        return $this->pendingStepFor($user) !== null;
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
