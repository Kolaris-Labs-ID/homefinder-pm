<?php

namespace App\Models\Hris\Traits;

use App\Models\Hris\PayrollApprovalLog;
use App\Models\Hris\PayrollWorkflowConfig;
use App\Models\Hris\PayrollWorkflowStep;
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
     * Check if this batch can be escalated to COO.
     * Only batches with 'pending_finance' status can be escalated.
     */
    public function canBeEscalatedToCoo(): bool
    {
        return $this->status === 'pending_finance';
    }

    /**
     * Check if this batch can be approved by the COO.
     * Only batches with 'pending_coo' status can be approved by COO.
     */
    public function canBeApprovedByCoo(): bool
    {
        return $this->status === 'pending_coo';
    }

    /**
     * Check if this batch can be rejected.
     * Batches in 'pending_finance' or 'pending_coo' status can be rejected.
     */
    public function canBeRejected(): bool
    {
        return in_array($this->status, ['pending_finance', 'pending_coo']);
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

        throw new \InvalidArgumentException('Unsupported batch type: ' . get_class($this));
    }

    /**
     * Scope to filter batches visible to a given user based on their role
     * and approval step assignment.
     *
     * - HR/admin/super_admin sees all batches
     * - Finance approver sees pending_finance and paid batches
     * - COO approver sees pending_coo and paid batches
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

        if (!$config) {
            // No active config — no access for non-HR users
            return $query->whereRaw('1 = 0');
        }

        $visibleStatuses = [];

        // Check if user is the finance approver
        $financeStep = $config->steps()->where('step_type', PayrollWorkflowStep::STEP_FINANCE)->first();
        if ($financeStep && $financeStep->canBeActedOnBy($user)) {
            $visibleStatuses = array_merge($visibleStatuses, ['pending_finance', 'paid']);
        }

        // Check if user is the COO approver
        $cooStep = $config->steps()->where('step_type', PayrollWorkflowStep::STEP_COO)->first();
        if ($cooStep && $cooStep->canBeActedOnBy($user)) {
            $visibleStatuses = array_merge($visibleStatuses, ['pending_coo', 'paid']);
        }

        if (empty($visibleStatuses)) {
            // User is not an approver — no access
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('status', array_unique($visibleStatuses));
    }
}
