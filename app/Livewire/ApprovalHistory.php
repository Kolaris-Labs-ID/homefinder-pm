<?php

namespace App\Livewire;

use App\Contracts\Hris\ApprovableContract;
use App\Models\Hris\ApprovalStep;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * ApprovalHistory Livewire component.
 *
 * Displays a timeline of approval steps for an approvable model,
 * showing approver name, timestamp, status, and notes for each step.
 *
 * Requirements: 1.2, 1.4
 */
class ApprovalHistory extends Component
{
    /**
     * The approvable model type (class name).
     */
    public string $approvableType;

    /**
     * The approvable model ID.
     */
    public int $approvableId;

    /**
     * Mount the component with the approvable model.
     */
    public function mount(Model $approvable): void
    {
        $this->approvableType = get_class($approvable);
        $this->approvableId = $approvable->getKey();
    }

    /**
     * Get the approvable model instance.
     */
    #[Computed]
    public function approvable(): ?ApprovableContract
    {
        $model = $this->approvableType::find($this->approvableId);

        if ($model instanceof ApprovableContract) {
            return $model;
        }

        return null;
    }

    /**
     * Get the approval steps for the timeline.
     */
    #[Computed]
    public function approvalSteps(): Collection
    {
        $approvable = $this->approvable;

        if (!$approvable) {
            return collect();
        }

        return $approvable->approvalSteps()
            ->with('approver')
            ->orderBy('step_order')
            ->get();
    }

    /**
     * Get the step that was actually approved or rejected (if any).
     * Used to tell non-acting approvers who acted on the request.
     */
    #[Computed]
    public function actedStep(): ?ApprovalStep
    {
        return $this->approvalSteps
            ->whereIn('status', [ApprovalStep::STATUS_APPROVED, ApprovalStep::STATUS_REJECTED])
            ->first();
    }

    /**
     * Get the status configuration for display.
     */
    public function getStatusConfig(string $status): array
    {
        return match ($status) {
            ApprovalStep::STATUS_APPROVED => [
                'color' => 'bg-green-500',
                'bgLight' => 'bg-green-50 dark:bg-green-900/20',
                'textColor' => 'text-green-700 dark:text-green-300',
                'borderColor' => 'border-green-500',
                'icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                'label' => 'Approved',
            ],
            ApprovalStep::STATUS_REJECTED => [
                'color' => 'bg-red-500',
                'bgLight' => 'bg-red-50 dark:bg-red-900/20',
                'textColor' => 'text-red-700 dark:text-red-300',
                'borderColor' => 'border-red-500',
                'icon' => 'M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                'label' => 'Rejected',
            ],
            ApprovalStep::STATUS_PENDING => [
                'color' => 'bg-amber-500',
                'bgLight' => 'bg-amber-50 dark:bg-amber-900/20',
                'textColor' => 'text-amber-700 dark:text-amber-300',
                'borderColor' => 'border-amber-500',
                'icon' => 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
                'label' => 'Pending',
            ],
            ApprovalStep::STATUS_SKIPPED => [
                'color' => 'bg-gray-400',
                'bgLight' => 'bg-gray-50 dark:bg-gray-800',
                'textColor' => 'text-gray-600 dark:text-gray-400',
                'borderColor' => 'border-gray-400',
                'icon' => 'M3 8.688c0-.864.933-1.405 1.683-.977l7.108 4.062a1.125 1.125 0 010 1.953l-7.108 4.062A1.125 1.125 0 013 16.81V8.688zM12.75 8.688c0-.864.933-1.405 1.683-.977l7.108 4.062a1.125 1.125 0 010 1.953l-7.108 4.062a1.125 1.125 0 01-1.683-.977V8.688z',
                'label' => 'Skipped',
            ],
            default => [
                'color' => 'bg-gray-400',
                'bgLight' => 'bg-gray-50 dark:bg-gray-800',
                'textColor' => 'text-gray-600 dark:text-gray-400',
                'borderColor' => 'border-gray-400',
                'icon' => 'M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z',
                'label' => 'Unknown',
            ],
        };
    }

    /**
     * Get the approver display name for a step.
     */
    public function getApproverName(ApprovalStep $step): string
    {
        if ($step->approver) {
            return $step->approver->name;
        }

        if ($step->approver_role) {
            return "Role: {$step->approver_role}";
        }

        return 'Unknown Approver';
    }

    /**
     * Check if the step has been processed (approved or rejected).
     */
    public function isProcessed(ApprovalStep $step): bool
    {
        return in_array($step->status, [
            ApprovalStep::STATUS_APPROVED,
            ApprovalStep::STATUS_REJECTED,
            ApprovalStep::STATUS_SKIPPED,
        ]);
    }

    /**
     * Render the component.
     */
    public function render(): View
    {
        return view('livewire.approval-history');
    }
}
