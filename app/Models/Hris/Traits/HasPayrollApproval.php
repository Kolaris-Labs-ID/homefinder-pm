<?php

namespace App\Models\Hris\Traits;

use App\Models\Hris\LatePayCompensationBatch;
use App\Models\Hris\OvertimePayBatch;
use App\Models\Hris\PayrollApprovalLog;
use App\Models\Hris\PayrollWorkflowConfig;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasPayrollApproval
{
    /**
     * Get all approval log entries for this batch.
     */
    public function approvalLogs(): MorphMany
    {
        return $this->morphMany(PayrollApprovalLog::class, 'batch')
            ->orderBy('created_at', 'asc');
    }

    /**
     * Get the user who submitted this batch for approval.
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Check if this batch can be submitted for approval.
     * Only batches with 'calculated' status can be submitted.
     */
    public function canSubmitForApproval(): bool
    {
        return $this->status === 'calculated';
    }

    /**
     * Check if this batch can be approved by a Finance approver.
     * Only batches with 'pending_finance' status can be approved by Finance.
     */
    public function canBeApprovedByFinance(): bool
    {
        return $this->status === 'pending_finance';
    }

    /**
     * Check if this batch can be rejected.
     * Batches in 'pending_finance' status can be rejected.
     */
    public function canBeRejected(): bool
    {
        return $this->status === 'pending_finance';
    }

    /**
     * Check if this batch can be resubmitted after rejection.
     * Only batches with 'rejected' status can be resubmitted.
     */
    public function canBeResubmitted(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if this batch has been paid.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Get the workflow module identifier for this batch model.
     */
    public function getWorkflowModule(): string
    {
        if ($this instanceof PayrunBatch) {
            return PayrollWorkflowConfig::MODULE_MONTHLY;
        }

        if ($this instanceof WeeklyAllowanceBatch) {
            return PayrollWorkflowConfig::MODULE_WEEKLY;
        }

        if ($this instanceof OvertimePayBatch) {
            return PayrollWorkflowConfig::MODULE_OVERTIME;
        }

        if ($this instanceof LatePayCompensationBatch) {
            return PayrollWorkflowConfig::MODULE_LATE_PAY_COMPENSATION;
        }

        throw new \InvalidArgumentException('Unsupported batch type: '.get_class($this));
    }

    /**
     * Scope to filter batches visible to a given user based on their role
     * and approval step assignment.
     *
     * - HR/admin/super_admin sees all batches
     * - Finance approver (any of the configured finance users) sees pending_finance and paid batches
     * - Other users see nothing
     */
    public function scopeVisibleToUser(Builder $query, User $user): Builder
    {
        // HR, admin, and super_admin see everything
        if ($user->hasRole(['super_admin', 'admin', 'hr'])) {
            return $query;
        }

        // Determine the module for this batch model
        $module = $this->getWorkflowModule();
        $config = PayrollWorkflowConfig::getActiveConfig($module);

        if (! $config) {
            // No active config — no access for non-HR users
            return $query->whereRaw('1 = 0');
        }

        $financeSteps = $config->getFinanceStepsOrdered();
        $isFinanceApprover = $financeSteps->contains(fn ($step) => $step->canBeActedOnBy($user));

        if ($isFinanceApprover) {
            return $query->whereIn('status', ['pending_finance', 'paid']);
        }

        // User is not an approver — no access
        return $query->whereRaw('1 = 0');
    }

    /**
     * Get the name of the current sequential approver (if pending_finance).
     */
    public function getCurrentApproverNameAttribute(): ?string
    {
        if ($this->status !== 'pending_finance' || ! $this->current_finance_step_order) {
            return null;
        }

        $module = $this->getWorkflowModule();
        $config = PayrollWorkflowConfig::getActiveConfig($module);

        if (! $config) {
            return null;
        }

        $steps = $config->getFinanceStepsOrdered();
        $currentStep = $steps->get($this->current_finance_step_order - 1);

        if ($currentStep && $currentStep->assigneeUser) {
            return $currentStep->assigneeUser->name;
        }

        return null;
    }
}
