<?php

namespace App\Services\Hris;

use App\Models\Hris\PayrollApprovalLog;
use App\Models\Hris\PayrollWorkflowConfig;
use App\Models\Hris\PayrollWorkflowStep;
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

            if (!$batch->canSubmitForApproval()) {
                throw new \LogicException("Cannot submit batch with status: {$batch->status}");
            }

            $module = $this->getModuleForBatch($batch);
            $config = PayrollWorkflowConfig::getActiveConfig($module);

            if (!$config) {
                throw new \InvalidArgumentException(
                    "No active workflow configuration exists for module: {$module}"
                );
            }

            $previousStatus = $batch->status;
            $batch->status = 'pending_finance';
            $batch->submitted_by = $submitter->id;
            $batch->submitted_at = now();
            $batch->save();

            $this->createLog($batch, 'submitted', $previousStatus, 'pending_finance', $submitter);
        });
    }

    /**
     * Finance approves and pays (pending_finance → paid).
     * Any of the configured finance approvers can approve.
     */
    public function approveAndPay(Model $batch, User $approver, ?string $notes = null): void
    {
        DB::transaction(function () use ($batch, $approver, $notes) {
            $batch = $batch->fresh();
            $batch->lockForUpdate()->find($batch->id);

            if ($batch->isPaid()) {
                throw new \LogicException("Cannot approve batch with status: {$batch->status}");
            }

            if (!$batch->canBeApprovedByFinance()) {
                throw new \LogicException("Cannot approve batch with status: {$batch->status}");
            }

            $this->validateUserCanActOnStep($batch, $approver, PayrollWorkflowStep::STEP_FINANCE);

            $previousStatus = $batch->status;
            $batch->status = 'paid';
            $batch->paid_by = $approver->id;
            $batch->paid_at = now();
            $batch->save();

            $this->createLog($batch, 'approved', $previousStatus, 'paid', $approver, $notes);
        });
    }

    /**
     * Reject a batch (pending_finance → rejected).
     * Any of the configured finance approvers can reject.
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

            if (!$batch->canBeRejected()) {
                throw new \LogicException("Cannot reject batch with status: {$batch->status}");
            }

            $previousStatus = $batch->status;
            $batch->status = 'rejected';
            $batch->rejection_reason = $trimmedReason;
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

            if (!$batch->canBeResubmitted()) {
                throw new \LogicException("Cannot resubmit batch with status: {$batch->status}");
            }

            $previousStatus = $batch->status;
            $batch->status = 'pending_finance';
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

        throw new \InvalidArgumentException('Unsupported batch type: ' . get_class($batch));
    }

    /**
     * Check if a user can act on the current step for a batch.
     * Checks all finance step rows (multi-finance approver).
     */
    public function canUserActOnBatch(Model $batch, User $user): bool
    {
        $module = $this->getModuleForBatch($batch);
        $config = PayrollWorkflowConfig::getActiveConfig($module);

        if (!$config) {
            return false;
        }

        if ($batch->status !== 'pending_finance') {
            return false;
        }

        // Check if user matches ANY of the finance step rows
        $financeSteps = $config->steps()->where('step_type', PayrollWorkflowStep::STEP_FINANCE)->get();

        return $financeSteps->contains(fn ($step) => $step->canBeActedOnBy($user));
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

    /**
     * Validate that a user can act on a specific step type for a batch.
     * For finance steps, checks ALL finance step rows (multi-finance approver).
     */
    protected function validateUserCanActOnStep(Model $batch, User $user, string $stepType): void
    {
        $module = $this->getModuleForBatch($batch);
        $config = PayrollWorkflowConfig::getActiveConfig($module);

        if (!$config) {
            throw new AuthorizationException('No active workflow configuration exists.');
        }

        $steps = $config->steps()->where('step_type', $stepType)->get();

        if ($steps->isEmpty()) {
            throw new AuthorizationException('You are not authorized to act on this batch.');
        }

        $canAct = $steps->contains(fn ($step) => $step->canBeActedOnBy($user));

        if (!$canAct) {
            throw new AuthorizationException('You are not authorized to act on this batch.');
        }
    }
}
