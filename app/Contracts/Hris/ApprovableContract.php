<?php

namespace App\Contracts\Hris;

use App\Models\Hris\ApprovalStep;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

/**
 * Contract for models that require approval workflow.
 *
 * This interface defines the methods that approvable models must implement
 * to integrate with the approval workflow system.
 */
interface ApprovableContract
{
    /**
     * Get the approval steps for this approvable item.
     */
    public function approvalSteps(): MorphMany;

    /**
     * Get the current pending approval step.
     */
    public function currentApprovalStep(): ?ApprovalStep;

    /**
     * Check if the item is approved.
     */
    public function isApproved(): bool;

    /**
     * Check if the item is pending approval.
     */
    public function isPending(): bool;

    /**
     * Check if the item is rejected.
     */
    public function isRejected(): bool;

    /**
     * Get the approval history for this item.
     */
    public function getApprovalHistory(): Collection;

    /**
     * Mark the item as approved.
     */
    public function markAsApproved(): void;

    /**
     * Mark the item as rejected.
     */
    public function markAsRejected(string $reason): void;

    /**
     * Get the module type for this approvable (leave, reimbursement, etc.).
     */
    public function getApprovalModule(): string;
}
