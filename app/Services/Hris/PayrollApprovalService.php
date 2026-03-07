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
     * COO approves and pays (pending_coo → paid).
     */
    public function cooApproveAndPay(Model $batch, User $approver, ?string $notes = null): void
    {
        DB::transaction(function () use ($batch, $approver, $notes) {
            $batch = $batch->fresh();
            $batch->lockForUpdate()->find($batch->id);

            if ($batch->isPaid()) {
                throw new \LogicException("Cannot approve batch with status: {$batch->status}");
            }

            if (!$batch->canBeApprovedByCoo()) {
                throw new \LogicException("Cannot approve batch with status: {$batch->status}");
            }

            $this->validateUserCanActOnStep($batch, $approver, PayrollWorkflowStep::STEP_COO);

            $previousStatus = $batch->status;
            $batch->status = 'paid';
            $batch->paid_by = $approver->id;
            $batch->paid_at = now();
            $batch->save();

            $this->createLog($batch, 'approved', $previousStatus, 'paid', $approver, $notes);
        });
    }

    /**
     * Finance escalates to COO (pending_finance → pending_coo).
     */
    public function escalateToCoo(Model $batch, User $escalator, ?string $notes = null): void
    {
        DB::transaction(function () use ($batch, $escalator, $notes) {
            $batch = $batch->fresh();
            $batch->lockForUpdate()->find($batch->id);

            if ($batch->isPaid()) {
                throw new \LogicException("Cannot escalate batch with status: {$batch->status}");
            }

            if (!$batch->canBeEscalatedToCoo()) {
                throw new \LogicException("Cannot escalate batch with status: {$batch->status}");
            }

            $module = $this->getModuleForBatch($batch);
            $config = PayrollWorkflowConfig::getActiveConfig($module);

            if (!$config) {
                throw new \LogicException("No active workflow configuration exists for module: {$module}");
            }

            $cooStep = $config->steps()->where('step_type', PayrollWorkflowStep::STEP_COO)->first();

            if (!$cooStep) {
                throw new \LogicException("No COO step configured for module: {$module}");
            }

            $this->validateUserCanActOnStep($batch, $escalator, PayrollWorkflowStep::STEP_FINANCE);

            $previousStatus = $batch->status;
            $batch->status = 'pending_coo';
            $batch->save();

            $this->createLog($batch, 'escalated', $previousStatus, 'pending_coo', $escalator, $notes);
        });
    }

    /**
     * Reject a batch (pending_finance|pending_coo → rejected).
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
     */
    public function canUserActOnBatch(Model $batch, User $user): bool
    {
        $module = $this->getModuleForBatch($batch);
        $config = PayrollWorkflowConfig::getActiveConfig($module);

        if (!$config) {
            return false;
        }

        $stepType = match ($batch->status) {
            'pending_finance' => PayrollWorkflowStep::STEP_FINANCE,
            'pending_coo' => PayrollWorkflowStep::STEP_COO,
            default => null,
        };

        if (!$stepType) {
            return false;
        }

        $step = $config->steps()->where('step_type', $stepType)->first();

        return $step && $step->canBeActedOnBy($user);
    }

    /**
     * Check if escalation to COO is available for a batch's workflow config.
     */
    public function isEscalationAvailable(Model $batch): bool
    {
        $module = $this->getModuleForBatch($batch);
        $config = PayrollWorkflowConfig::getActiveConfig($module);

        if (!$config) {
            return false;
        }

        return $config->steps()->where('step_type', PayrollWorkflowStep::STEP_COO)->exists();
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
     */
    protected function validateUserCanActOnStep(Model $batch, User $user, string $stepType): void
    {
        $module = $this->getModuleForBatch($batch);
        $config = PayrollWorkflowConfig::getActiveConfig($module);

        if (!$config) {
            throw new AuthorizationException('No active workflow configuration exists.');
        }

        $step = $config->steps()->where('step_type', $stepType)->first();

        if (!$step || !$step->canBeActedOnBy($user)) {
            throw new AuthorizationException('You are not authorized to act on this batch.');
        }
    }
}
