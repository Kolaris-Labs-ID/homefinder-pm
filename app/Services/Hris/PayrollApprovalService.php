<?php

namespace App\Services\Hris;

use App\Models\Hris\LatePayCompensationBatch;
use App\Models\Hris\OvertimePayBatch;
use App\Models\Hris\PayrollApprovalLog;
use App\Models\Hris\PayrollWorkflowConfig;
use App\Models\Hris\PayrunBatch;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PayrollApprovalService
{
    /**
     * Submit a batch for approval (calculated → pending_finance).
     */
    public function submitForApproval(Model $batch, User $submitter): void
    {
        DB::transaction(function () use ($batch, $submitter) {
            $batch = $batch->fresh();
            $batch->lockForUpdate()->find($batch->id);

            if ($batch->isPaid()) {
                throw new \LogicException("Cannot submit batch with status: {$batch->status}");
            }

            if (! $batch->canSubmitForApproval()) {
                throw new \LogicException("Cannot submit batch with status: {$batch->status}");
            }

            $module = $this->getModuleForBatch($batch);
            $config = PayrollWorkflowConfig::getActiveConfig($module);

            if (! $config) {
                throw new \InvalidArgumentException(
                    "No active workflow configuration exists for module: {$module}"
                );
            }

            $previousStatus = $batch->status;
            $batch->status = 'pending_finance';
            $batch->current_finance_step_order = 1;
            $batch->submitted_by = $submitter->id;
            $batch->submitted_at = now();
            $batch->save();

            $this->createLog($batch, 'submitted', $previousStatus, 'pending_finance', $submitter);
        });
    }

    /**
     * Intermediate Finance Approver approves (stays pending_finance, advances step order).
     */
    public function approve(Model $batch, User $approver, ?string $notes = null): void
    {
        DB::transaction(function () use ($batch, $approver, $notes) {
            $batch = $batch->fresh();
            $batch->lockForUpdate()->find($batch->id);

            if ($batch->status !== 'pending_finance') {
                throw new \LogicException("Cannot approve batch with status: {$batch->status}");
            }

            if (! $this->canUserActOnBatch($batch, $approver)) {
                throw new AuthorizationException('You are not authorized to act on this step.');
            }

            if ($this->isFinalFinanceStep($batch)) {
                throw new \LogicException('Use approveAndPay for the final step.');
            }

            $batch->current_finance_step_order += 1;
            $batch->save();

            $this->createLog($batch, 'approved', 'pending_finance', 'pending_finance', $approver, $notes);
        });
    }

    /**
     * Final Finance Approver approves and pays (pending_finance → paid).
     */
    public function approveAndPay(Model $batch, User $approver, ?string $notes = null): void
    {
        DB::transaction(function () use ($batch, $approver, $notes) {
            $batch = $batch->fresh();
            $batch->lockForUpdate()->find($batch->id);

            if ($batch->isPaid()) {
                throw new \LogicException("Cannot approve batch with status: {$batch->status}");
            }

            if (! $batch->canBeApprovedByFinance()) {
                throw new \LogicException("Cannot approve batch with status: {$batch->status}");
            }

            if (! $this->canUserActOnBatch($batch, $approver)) {
                throw new AuthorizationException('You are not authorized to act on this step.');
            }

            if (! $this->isFinalFinanceStep($batch)) {
                throw new AuthorizationException('Only the final approver can pay the batch.');
            }

            $previousStatus = $batch->status;
            $batch->status = 'paid';
            $batch->paid_by = $approver->id;
            $batch->paid_at = now();
            $batch->save();

            $this->createLog($batch, 'paid', $previousStatus, 'paid', $approver, $notes);
        });
    }

    /**
     * Reject a batch (pending_finance → rejected).
     */
    public function reject(Model $batch, User $rejector, string $reason): void
    {
        $trimmedReason = trim($reason);

        if ($trimmedReason === '') {
            throw new \InvalidArgumentException('Rejection reason cannot be empty.');
        }

        DB::transaction(function () use ($batch, $rejector, $trimmedReason) {
            $batch = $batch->fresh();
            $batch->lockForUpdate()->find($batch->id);

            if ($batch->isPaid()) {
                throw new \LogicException("Cannot reject batch with status: {$batch->status}");
            }

            if (! $batch->canBeRejected()) {
                throw new \LogicException("Cannot reject batch with status: {$batch->status}");
            }

            if (! $this->canUserActOnBatch($batch, $rejector)) {
                throw new AuthorizationException('You are not authorized to act on this step.');
            }

            $previousStatus = $batch->status;
            $batch->status = 'rejected';
            $batch->rejection_reason = $trimmedReason;
            $batch->current_finance_step_order = null; // Reset order upon rejection
            $batch->save();

            $this->createLog($batch, 'rejected', $previousStatus, 'rejected', $rejector, $trimmedReason);
        });
    }

    /**
     * Resubmit a rejected batch (rejected → pending_finance).
     */
    public function resubmit(Model $batch, User $submitter): void
    {
        DB::transaction(function () use ($batch, $submitter) {
            $batch = $batch->fresh();
            $batch->lockForUpdate()->find($batch->id);

            if ($batch->isPaid()) {
                throw new \LogicException("Cannot resubmit batch with status: {$batch->status}");
            }

            if (! $batch->canBeResubmitted()) {
                throw new \LogicException("Cannot resubmit batch with status: {$batch->status}");
            }

            $previousStatus = $batch->status;
            $batch->status = 'pending_finance';
            $batch->current_finance_step_order = 1; // Restart from step 1
            $batch->save();

            $this->createLog($batch, 'resubmitted', $previousStatus, 'pending_finance', $submitter);
        });
    }

    /**
     * Get the workflow module identifier for a batch model.
     */
    public function getModuleForBatch(Model $batch): string
    {
        if ($batch instanceof PayrunBatch) {
            return PayrollWorkflowConfig::MODULE_MONTHLY;
        }

        if ($batch instanceof WeeklyAllowanceBatch) {
            return PayrollWorkflowConfig::MODULE_WEEKLY;
        }

        if ($batch instanceof OvertimePayBatch) {
            return PayrollWorkflowConfig::MODULE_OVERTIME;
        }

        if ($batch instanceof LatePayCompensationBatch) {
            return PayrollWorkflowConfig::MODULE_LATE_PAY_COMPENSATION;
        }

        throw new \InvalidArgumentException('Unsupported batch type: '.get_class($batch));
    }

    /**
     * Check if a user can act on the CURRENT step for a batch.
     */
    public function canUserActOnBatch(Model $batch, User $user): bool
    {
        if ($batch->status !== 'pending_finance') {
            return false;
        }

        $module = $this->getModuleForBatch($batch);
        $config = PayrollWorkflowConfig::getActiveConfig($module);

        if (! $config || ! $batch->current_finance_step_order) {
            return false;
        }

        $steps = $config->getFinanceStepsOrdered();
        $currentStep = $steps->get($batch->current_finance_step_order - 1);

        if (! $currentStep) {
            return false;
        }

        return $currentStep->canBeActedOnBy($user);
    }

    /**
     * Check if the batch is currently at the final finance step.
     */
    public function isFinalFinanceStep(Model $batch): bool
    {
        $module = $this->getModuleForBatch($batch);
        $config = PayrollWorkflowConfig::getActiveConfig($module);

        if (! $config || ! $batch->current_finance_step_order) {
            return false;
        }

        $steps = $config->getFinanceStepsOrdered();

        return $batch->current_finance_step_order >= $steps->count();
    }

    /**
     * Create an approval log entry.
     */
    protected function createLog(
        Model $batch,
        string $action,
        string $previousStatus,
        string $newStatus,
        User $actor,
        ?string $notes = null
    ): PayrollApprovalLog {
        return PayrollApprovalLog::create([
            'batch_type' => get_class($batch),
            'batch_id' => $batch->id,
            'action' => $action,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'acted_by' => $actor->id,
            'notes' => $notes,
        ]);
    }
}
