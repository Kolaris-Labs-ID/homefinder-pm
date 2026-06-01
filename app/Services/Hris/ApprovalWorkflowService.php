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
     * Get the active workflow for a specific module.
     * Only returns workflows where is_active = true.
     *
     * @param  string  $module  The module type (leave, reimbursement, payroll_adjustment)
     */
    public function getActiveWorkflowForModule(string $module): ?ApprovalWorkflow
    {
        return ApprovalWorkflow::where('for_module', $module)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Initialize a workflow for an approvable item.
     * Only uses active workflows - inactive workflows are ignored.
     *
     * @param  Model  $approvable  The model that needs approval (LeaveRequest, etc.)
     * @param  string  $module  The module type (leave, reimbursement, etc.)
     */
    public function initializeWorkflow(Model $approvable, string $module): void
    {
        // Find applicable active workflow for the module
        // IMPORTANT: Only active workflows are used for new requests (Requirements 6.4)
        $workflow = $this->getActiveWorkflowForModule($module);

        if (! $workflow || empty($workflow->steps)) {
            // No active workflow found, use default behavior
            return;
        }

        // Create workflow snapshot to preserve configuration at initialization time
        // This ensures in-progress approvals use original configuration even if workflow is modified
        $workflowSnapshot = [
            'workflow_id' => $workflow->id,
            'workflow_name' => $workflow->name,
            'for_module' => $workflow->for_module,
            'steps' => $workflow->steps,
            'snapshot_at' => now()->toIso8601String(),
        ];

        // Create ApprovalStep records from workflow steps
        $steps = $workflow->steps;
        $firstStep = true;

        foreach ($steps as $stepDefinition) {
            $approverId = $this->resolveApprover($stepDefinition, $approvable);

            ApprovalStep::create([
                'workflow_id' => $workflow->id,
                'workflow_snapshot' => $workflowSnapshot,
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
     * @param  array  $stepDefinition  The step definition with type and value
     * @param  Model  $approvable  The approvable model for context
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
     * @throws \Exception
     */
    public function approve(ApprovalStep $step, User $approver, ?string $notes = null): void
    {
        // Validate the step is pending
        if ($step->status !== ApprovalStep::STATUS_PENDING) {
            throw new \Exception('This step has already been processed.');
        }

        // Validate the approver is authorized
        if (! $this->isUserAuthorizedForStep($step, $approver)) {
            throw new \Exception('You are not authorized to approve this step.');
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
     * Approve a step using parallel (any-one-approves) logic.
     * When one approver approves, the approvable is immediately marked as approved
     * and all remaining pending steps are marked as skipped.
     *
     * Used for modules like Leave where any single approval is sufficient.
     *
     * @throws \Exception
     */
    public function approveParallel(ApprovalStep $step, User $approver, ?string $notes = null): void
    {
        // Validate the step is pending
        if ($step->status !== ApprovalStep::STATUS_PENDING) {
            throw new \Exception('This step has already been processed.');
        }

        // Validate the approver is authorized
        if (! $this->isUserAuthorizedForStep($step, $approver)) {
            throw new \Exception('You are not authorized to approve this step.');
        }

        // Mark current step as approved
        $step->update([
            'status' => ApprovalStep::STATUS_APPROVED,
            'approved_at' => now(),
            'notes' => $notes,
            'approver_id' => $approver->id,
        ]);

        $approvable = $step->approvable;

        // Skip all remaining pending steps for this approvable
        ApprovalStep::where('approvable_type', get_class($approvable))
            ->where('approvable_id', $approvable->id)
            ->where('status', ApprovalStep::STATUS_PENDING)
            ->update(['status' => ApprovalStep::STATUS_SKIPPED]);

        // Immediately mark the approvable as approved
        $this->markApprovableAsApproved($approvable);
    }

    /**
     * Initialize a parallel workflow where all steps start as pending
     * and all approvers are notified simultaneously.
     *
     * Used for modules like Leave where any single approval is sufficient.
     */
    public function initializeParallelWorkflow(Model $approvable, string $module): void
    {
        $workflow = $this->getActiveWorkflowForModule($module);

        if (! $workflow || empty($workflow->steps)) {
            return;
        }

        // Create workflow snapshot
        $workflowSnapshot = [
            'workflow_id' => $workflow->id,
            'workflow_name' => $workflow->name,
            'for_module' => $workflow->for_module,
            'steps' => $workflow->steps,
            'snapshot_at' => now()->toIso8601String(),
        ];

        // Create ALL steps as pending (parallel)
        $steps = $workflow->steps;

        foreach ($steps as $stepDefinition) {
            $approverId = $this->resolveApprover($stepDefinition, $approvable);

            ApprovalStep::create([
                'workflow_id' => $workflow->id,
                'workflow_snapshot' => $workflowSnapshot,
                'approvable_type' => get_class($approvable),
                'approvable_id' => $approvable->id,
                'step_order' => $stepDefinition['order'],
                'approver_id' => $approverId,
                'approver_role' => $stepDefinition['type'] === 'role' ? $stepDefinition['value'] : null,
                'status' => ApprovalStep::STATUS_PENDING, // All pending
                'approved_at' => null,
                'notes' => null,
            ]);
        }

        // Notify ALL approvers simultaneously
        $allSteps = ApprovalStep::where('approvable_type', get_class($approvable))
            ->where('approvable_id', $approvable->id)
            ->where('status', ApprovalStep::STATUS_PENDING)
            ->get();

        foreach ($allSteps as $step) {
            if ($step->approver_id) {
                $this->notifyApprover($step);
            }
        }
    }

    /**
     * Reject the current step.
     *
     * @throws \Exception
     */
    public function reject(ApprovalStep $step, User $approver, string $reason): void
    {
        // Validate the step is pending
        if ($step->status !== ApprovalStep::STATUS_PENDING) {
            throw new \Exception('This step has already been processed.');
        }

        // Validate the approver is authorized
        if (! $this->isUserAuthorizedForStep($step, $approver)) {
            throw new \Exception('You are not authorized to reject this step.');
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

        // Notify the requestor about the approval
        $this->notifyRequestorStatusChange($approvable, 'approved');
    }

    /**
     * Mark the approvable model as rejected.
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

        // Notify the requestor about the rejection
        $this->notifyRequestorStatusChange($approvable, 'rejected', $reason);
    }

    /**
     * Notify the requestor about a status change (approved or rejected).
     *
     * @param  string  $status  'approved' or 'rejected'
     * @param  string|null  $reason  Rejection reason (only for rejected status)
     */
    protected function notifyRequestorStatusChange(Model $approvable, string $status, ?string $reason = null): void
    {
        try {
            $requestorUserId = $this->getRequestorUserId($approvable);

            if (! $requestorUserId) {
                Log::warning('Could not find requestor user ID for notification', [
                    'approvable_type' => get_class($approvable),
                    'approvable_id' => $approvable->id,
                ]);

                return;
            }

            $approvableType = class_basename($approvable);
            $approvableUrl = $this->getApprovableUrl($approvable);

            $notificationType = $status === 'approved' ? 'approval_approved' : 'approval_rejected';
            $title = $status === 'approved' ? 'Request Approved' : 'Request Rejected';
            $message = $status === 'approved'
                ? "Your {$approvableType} has been approved"
                : "Your {$approvableType} has been rejected".($reason ? ": {$reason}" : '');

            Notification::create([
                'user_id' => $requestorUserId,
                'type' => $notificationType,
                'title' => $title,
                'message' => $message,
                'data' => [
                    'approvable_type' => get_class($approvable),
                    'approvable_id' => $approvable->id,
                    'approvable_url' => $approvableUrl,
                    'status' => $status,
                    'reason' => $reason,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send requestor status notification: '.$e->getMessage(), [
                'approvable_type' => get_class($approvable),
                'approvable_id' => $approvable->id,
                'status' => $status,
            ]);
        }
    }

    /**
     * Get the user ID of the requestor from an approvable model.
     */
    protected function getRequestorUserId(Model $approvable): ?int
    {
        // Try to get user_id from employee relationship
        if (method_exists($approvable, 'employee') && $approvable->employee) {
            $employee = $approvable->employee;
            if ($employee->user_id) {
                return $employee->user_id;
            }
        }

        // Fallback: check if approvable has direct user_id
        if (isset($approvable->user_id)) {
            return $approvable->user_id;
        }

        return null;
    }

    /**
     * Notify the approver about a pending approval.
     * This method creates a notification with a link to the approvable item.
     */
    protected function notifyApprover(ApprovalStep $step): void
    {
        if (! $step->approver_id) {
            // Try to resolve approver from role if no direct assignment
            if ($step->approver_role) {
                $user = User::whereHas('roles', function ($query) use ($step) {
                    $query->where('name', $step->approver_role);
                })->first();

                if ($user) {
                    $this->createApproverNotification($step, $user->id);
                }
            }

            return;
        }

        $this->createApproverNotification($step, $step->approver_id);
    }

    /**
     * Create the actual notification for an approver.
     */
    protected function createApproverNotification(ApprovalStep $step, int $approverId): void
    {
        try {
            $approvable = $step->approvable;
            $approvableType = class_basename($approvable);
            $approvableUrl = $this->getApprovableUrl($approvable);

            // Get requestor name if available
            $requestorName = $this->getRequestorName($approvable);
            $requestorInfo = $requestorName ? " from {$requestorName}" : '';

            Notification::create([
                'user_id' => $approverId,
                'type' => 'approval_pending',
                'title' => 'Approval Required',
                'message' => "A {$approvableType}{$requestorInfo} requires your approval (Step {$step->step_order})",
                'data' => [
                    'approvable_type' => get_class($approvable),
                    'approvable_id' => $approvable->id,
                    'step_id' => $step->id,
                    'step_order' => $step->step_order,
                    'approvable_url' => $approvableUrl,
                    'requestor_name' => $requestorName,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send approval notification: '.$e->getMessage(), [
                'step_id' => $step->id,
                'approver_id' => $approverId,
            ]);
        }
    }

    /**
     * Get the URL to view/edit the approvable item.
     */
    protected function getApprovableUrl(Model $approvable): ?string
    {
        try {
            $class = get_class($approvable);

            return match ($class) {
                \App\Models\Hris\LeaveRequest::class => route('filament.admin.resources.leave-requests.edit', ['record' => $approvable->id]),
                \App\Models\Hris\Reimbursement::class => route('filament.admin.resources.reimbursements.edit', ['record' => $approvable->id]),
                \App\Models\Hris\PayrollRecord::class => route('filament.admin.resources.payrun-batches.index'),
                \App\Models\Hris\TrainingRecord::class => route('filament.admin.resources.training-records.edit', ['record' => $approvable->id]),
                \App\Models\Hris\ExpenseRequest::class => route('filament.admin.resources.expense-requests.view', ['record' => $approvable->id]),
                \App\Models\Hris\PaymentRequest::class => route('filament.admin.resources.payment-requests.view', ['record' => $approvable->id]),
                default => null,
            };
        } catch (\Exception $e) {
            Log::warning('Failed to generate approvable URL: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Get the requestor name from an approvable model.
     */
    protected function getRequestorName(Model $approvable): ?string
    {
        // Try to get employee name
        if (method_exists($approvable, 'employee') && $approvable->employee) {
            $employee = $approvable->employee;
            if ($employee->user) {
                return $employee->user->name;
            }
            // Fallback to employee's full_name if available
            if (isset($employee->full_name)) {
                return $employee->full_name;
            }
        }

        return null;
    }

    /**
     * Get all pending approval steps for a given user.
     * Supports both direct user assignment and role-based assignment.
     */
    public function getPendingApprovalsForUser(User $user): \Illuminate\Database\Eloquent\Collection
    {
        // Get user's role names
        $userRoles = $user->roles->pluck('name')->toArray();

        return ApprovalStep::where('status', ApprovalStep::STATUS_PENDING)
            ->where(function ($query) use ($user, $userRoles) {
                // Direct user assignment
                $query->where('approver_id', $user->id);

                // Role-based assignment
                if (! empty($userRoles)) {
                    $query->orWhereIn('approver_role', $userRoles);
                }
            })
            ->with(['approvable', 'workflow'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Resolve a workflow module to its approvable model class (FQCN), or null if unknown.
     * Single registry for ER/PR + legacy modules; used by pending-approval queries.
     */
    public function moduleToClass(string $module): ?string
    {
        return [
            ApprovalWorkflow::MODULE_LEAVE => \App\Models\Hris\LeaveRequest::class,
            ApprovalWorkflow::MODULE_REIMBURSEMENT => \App\Models\Hris\Reimbursement::class,
            ApprovalWorkflow::MODULE_PAYROLL_ADJUSTMENT => \App\Models\Hris\PayrollRecord::class,
            ApprovalWorkflow::MODULE_TRAINING => \App\Models\Hris\TrainingRecord::class,
            ApprovalWorkflow::MODULE_EXPENSE_REQUEST => \App\Models\Hris\ExpenseRequest::class,
            ApprovalWorkflow::MODULE_PAYMENT_REQUEST => \App\Models\Hris\PaymentRequest::class,
        ][$module] ?? null;
    }

    /**
     * Get pending approval steps for a user filtered by module type.
     *
     * @param  string  $module  The module type (leave, reimbursement, payroll_adjustment, training)
     */
    public function getPendingApprovalsByModule(User $user, string $module): \Illuminate\Database\Eloquent\Collection
    {
        $approvableClass = $this->moduleToClass($module);

        if (! $approvableClass) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        // Get user's role names
        $userRoles = $user->roles->pluck('name')->toArray();

        return ApprovalStep::where('status', ApprovalStep::STATUS_PENDING)
            ->where('approvable_type', $approvableClass)
            ->where(function ($query) use ($user, $userRoles) {
                // Direct user assignment
                $query->where('approver_id', $user->id);

                // Role-based assignment
                if (! empty($userRoles)) {
                    $query->orWhereIn('approver_role', $userRoles);
                }
            })
            ->with(['approvable', 'workflow'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Bulk approve multiple approval steps in a single transaction.
     * Either all items are approved successfully, or none are modified (atomic).
     *
     * @param  array  $stepIds  Array of ApprovalStep IDs to approve
     * @param  User  $approver  The user performing the approval
     * @param  string|null  $notes  Optional notes for all approvals
     * @return array Array with 'success' boolean and 'results' array with status for each item
     */
    public function bulkApprove(array $stepIds, User $approver, ?string $notes = null): array
    {
        $results = [];

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($stepIds, $approver, $notes, &$results) {
                foreach ($stepIds as $stepId) {
                    $step = ApprovalStep::find($stepId);

                    if (! $step) {
                        throw new \Exception("Approval step {$stepId} not found.");
                    }

                    // Validate the step is pending
                    if ($step->status !== ApprovalStep::STATUS_PENDING) {
                        throw new \Exception("Step {$stepId} has already been processed.");
                    }

                    // Validate the approver is authorized
                    $isAuthorized = $this->isUserAuthorizedForStep($step, $approver);
                    if (! $isAuthorized) {
                        throw new \Exception("User is not authorized to approve step {$stepId}.");
                    }

                    // Approve the step (this will handle workflow progression)
                    $this->approve($step, $approver, $notes);

                    $results[$stepId] = [
                        'success' => true,
                        'message' => 'Approved successfully',
                    ];
                }
            });

            return [
                'success' => true,
                'results' => $results,
            ];
        } catch (\Exception $e) {
            Log::error('Bulk approval failed: '.$e->getMessage(), [
                'step_ids' => $stepIds,
                'approver_id' => $approver->id,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'results' => [],
            ];
        }
    }

    /**
     * Check if a user is authorized to approve a specific step.
     */
    protected function isUserAuthorizedForStep(ApprovalStep $step, User $user): bool
    {
        // Direct user assignment
        if ($step->approver_id === $user->id) {
            return true;
        }

        // Role-based assignment
        if ($step->approver_role && $user->hasRole($step->approver_role)) {
            return true;
        }

        return false;
    }

    /**
     * Get approval statistics for a user.
     * Returns counts of pending, approved, and rejected items.
     */
    public function getApprovalStatistics(User $user): array
    {
        // Get user's role names
        $userRoles = $user->roles->pluck('name')->toArray();

        // Build base query for steps where user is the approver
        $baseQuery = function ($status) use ($user, $userRoles) {
            return ApprovalStep::where('status', $status)
                ->where(function ($query) use ($user, $userRoles) {
                    // Direct user assignment
                    $query->where('approver_id', $user->id);

                    // Role-based assignment
                    if (! empty($userRoles)) {
                        $query->orWhereIn('approver_role', $userRoles);
                    }
                })
                ->count();
        };

        return [
            'pending' => $baseQuery(ApprovalStep::STATUS_PENDING),
            'approved' => ApprovalStep::where('status', ApprovalStep::STATUS_APPROVED)
                ->where('approver_id', $user->id)
                ->count(),
            'rejected' => ApprovalStep::where('status', ApprovalStep::STATUS_REJECTED)
                ->where('approver_id', $user->id)
                ->count(),
        ];
    }
}
