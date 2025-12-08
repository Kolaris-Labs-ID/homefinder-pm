<?php

namespace App\Services\Hris;

use App\Models\Hris\ApprovalStep;
use App\Models\Hris\ApprovalWorkflow;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ApprovalWorkflowService
{
    /**
     * Initialize a workflow for an approvable item.
     *
     * @param Model $approvable The model that needs approval (LeaveRequest, etc.)
     * @param string $module The module type (leave, reimbursement, etc.)
     * @return void
     */
    public function initializeWorkflow(Model $approvable, string $module): void
    {
        // Find applicable active workflow for the module
        $workflow = ApprovalWorkflow::where('for_module', $module)
            ->where('is_active', true)
            ->first();

        if (!$workflow || empty($workflow->steps)) {
            // No workflow found, use default behavior
            return;
        }

        // Create ApprovalStep records from workflow steps
        $steps = $workflow->steps;
        $firstStep = true;

        foreach ($steps as $stepDefinition) {
            $approverId = $this->resolveApprover($stepDefinition, $approvable);

            ApprovalStep::create([
                'workflow_id' => $workflow->id,
                'approvable_type' => get_class($approvable),
                'approvable_id' => $approvable->id,
                'step_order' => $stepDefinition['order'],
                'approver_id' => $approverId,
                'approver_role' => $stepDefinition['type'] === 'role' ? $stepDefinition['value'] : null,
                'status' => $firstStep ? ApprovalStep::STATUS_PENDING : ApprovalStep::STATUS_SKIPPED,
                'approved_at' => null,
                'notes' => null,
            ]);

            $firstStep = false;
        }

        // Notify the first approver
        $firstApprovalStep = $this->getCurrentStep($approvable);
        if ($firstApprovalStep && $firstApprovalStep->approver_id) {
            $this->notifyApprover($firstApprovalStep);
        }
    }


    /**
     * Resolve the approver user ID from a step definition.
     *
     * @param array $stepDefinition The step definition with type and value
     * @param Model $approvable The approvable model for context
     * @return int|null The resolved user ID or null
     */
    protected function resolveApprover(array $stepDefinition, Model $approvable): ?int
    {
        $type = $stepDefinition['type'] ?? null;
        $value = $stepDefinition['value'] ?? null;

        if ($type === 'user') {
            // Direct user ID assignment
            return is_numeric($value) ? (int) $value : null;
        }

        if ($type === 'role') {
            // Role-based assignment - find a user with this role
            $user = User::whereHas('roles', function ($query) use ($value) {
                $query->where('name', $value);
            })->first();

            return $user?->id;
        }

        return null;
    }

    /**
     * Get the current pending step for an approvable item.
     *
     * @param Model $approvable
     * @return ApprovalStep|null
     */
    public function getCurrentStep(Model $approvable): ?ApprovalStep
    {
        return ApprovalStep::where('approvable_type', get_class($approvable))
            ->where('approvable_id', $approvable->id)
            ->where('status', ApprovalStep::STATUS_PENDING)
            ->orderBy('step_order')
            ->first();
    }

    /**
     * Get all approval steps for an approvable item.
     *
     * @param Model $approvable
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getSteps(Model $approvable)
    {
        return ApprovalStep::where('approvable_type', get_class($approvable))
            ->where('approvable_id', $approvable->id)
            ->orderBy('step_order')
            ->get();
    }

    /**
     * Approve the current step.
     *
     * @param ApprovalStep $step
     * @param User $approver
     * @param string|null $notes
     * @return void
     * @throws \Exception
     */
    public function approve(ApprovalStep $step, User $approver, ?string $notes = null): void
    {
        // Validate the step is pending
        if ($step->status !== ApprovalStep::STATUS_PENDING) {
            throw new \Exception('This step has already been processed.');
        }

        // Validate the approver is authorized
        if ($step->approver_id && $step->approver_id !== $approver->id) {
            // Check if user has the required role
            if ($step->approver_role && !$approver->hasRole($step->approver_role)) {
                throw new \Exception('You are not authorized to approve this step.');
            }
        }

        // Mark current step as approved
        $step->update([
            'status' => ApprovalStep::STATUS_APPROVED,
            'approved_at' => now(),
            'notes' => $notes,
            'approver_id' => $approver->id,
        ]);

        // Get the approvable model
        $approvable = $step->approvable;

        // Check if there are more steps
        $nextStep = $this->getNextStep($step);

        if ($nextStep) {
            // Advance to next step
            $nextStep->update([
                'status' => ApprovalStep::STATUS_PENDING,
            ]);

            // Notify the next approver
            if ($nextStep->approver_id) {
                $this->notifyApprover($nextStep);
            }
        } else {
            // Final step approved - mark approvable as approved
            $this->markApprovableAsApproved($approvable);
        }
    }


    /**
     * Reject the current step.
     *
     * @param ApprovalStep $step
     * @param User $approver
     * @param string $reason
     * @return void
     * @throws \Exception
     */
    public function reject(ApprovalStep $step, User $approver, string $reason): void
    {
        // Validate the step is pending
        if ($step->status !== ApprovalStep::STATUS_PENDING) {
            throw new \Exception('This step has already been processed.');
        }

        // Validate the approver is authorized
        if ($step->approver_id && $step->approver_id !== $approver->id) {
            // Check if user has the required role
            if ($step->approver_role && !$approver->hasRole($step->approver_role)) {
                throw new \Exception('You are not authorized to reject this step.');
            }
        }

        // Mark step as rejected
        $step->update([
            'status' => ApprovalStep::STATUS_REJECTED,
            'approved_at' => now(),
            'notes' => $reason,
            'approver_id' => $approver->id,
        ]);

        // Mark approvable as rejected
        $approvable = $step->approvable;
        $this->markApprovableAsRejected($approvable, $reason);
    }

    /**
     * Get the next step after the given step.
     *
     * @param ApprovalStep $currentStep
     * @return ApprovalStep|null
     */
    protected function getNextStep(ApprovalStep $currentStep): ?ApprovalStep
    {
        return ApprovalStep::where('approvable_type', $currentStep->approvable_type)
            ->where('approvable_id', $currentStep->approvable_id)
            ->where('step_order', '>', $currentStep->step_order)
            ->orderBy('step_order')
            ->first();
    }

    /**
     * Mark the approvable model as approved.
     *
     * @param Model $approvable
     * @return void
     */
    protected function markApprovableAsApproved(Model $approvable): void
    {
        if (method_exists($approvable, 'markAsApproved')) {
            $approvable->markAsApproved();
        } elseif (isset($approvable->status)) {
            $approvable->update([
                'status' => 'approved',
                'approved_at' => now(),
            ]);
        }
    }

    /**
     * Mark the approvable model as rejected.
     *
     * @param Model $approvable
     * @param string $reason
     * @return void
     */
    protected function markApprovableAsRejected(Model $approvable, string $reason): void
    {
        if (method_exists($approvable, 'markAsRejected')) {
            $approvable->markAsRejected($reason);
        } elseif (isset($approvable->status)) {
            $approvable->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);
        }
    }

    /**
     * Notify the approver about a pending approval.
     *
     * @param ApprovalStep $step
     * @return void
     */
    protected function notifyApprover(ApprovalStep $step): void
    {
        if (!$step->approver_id) {
            return;
        }

        try {
            $approvable = $step->approvable;
            $approvableType = class_basename($approvable);

            Notification::create([
                'user_id' => $step->approver_id,
                'type' => 'approval_pending',
                'title' => 'Approval Required',
                'message' => "A {$approvableType} requires your approval (Step {$step->step_order})",
                'data' => [
                    'approvable_type' => get_class($approvable),
                    'approvable_id' => $approvable->id,
                    'step_id' => $step->id,
                    'step_order' => $step->step_order,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send approval notification: ' . $e->getMessage(), [
                'step_id' => $step->id,
                'approver_id' => $step->approver_id,
            ]);
        }
    }
}
