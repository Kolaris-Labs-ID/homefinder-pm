<?php

namespace App\Filament\Resources\ReimbursementResource\Pages;

use App\Filament\Resources\ReimbursementResource;
use App\Models\Hris\Reimbursement;
use App\Services\Hris\ApprovalWorkflowService;
use App\Services\Hris\ReimbursementService;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\Livewire;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewReimbursement extends ViewRecord
{
    protected static string $resource = ReimbursementResource::class;

    /**
     * Get the infolist schema for the view page.
     * Includes the ApprovalHistory component when the record has approval workflow.
     */
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // Approval History Section - only shown when record has approval workflow
                Section::make('Approval History')
                    ->schema([
                        Livewire::make(\App\Livewire\ApprovalHistory::class)
                            ->data(['approvable' => $this->record]),
                    ])
                    ->visible(fn (): bool => $this->record->hasApprovalWorkflow())
                    ->collapsible(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn () => $this->record->status === 'pending'),

            // Workflow-based approval action
            Actions\Action::make('workflow_approve')
                ->label(__('Approve'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->modalHeading(__('Approve Reimbursement'))
                ->modalDescription(__('Are you sure you want to approve this reimbursement?'))
                ->modalSubmitActionLabel(__('Approve'))
                ->form([
                    Forms\Components\Textarea::make('notes')
                        ->label(__('Notes (Optional)'))
                        ->placeholder(__('Add any notes or comments...'))
                        ->rows(3)
                        ->maxLength(1000),
                ])
                ->visible(fn (): bool => $this->canApproveWithWorkflow())
                ->action(fn (array $data) => $this->handleWorkflowApprove($data)),

            // Workflow-based rejection action
            Actions\Action::make('workflow_reject')
                ->label(__('Reject'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->modalHeading(__('Reject Reimbursement'))
                ->modalDescription(__('Please provide a reason for rejecting this reimbursement.'))
                ->modalSubmitActionLabel(__('Reject'))
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label(__('Rejection Reason'))
                        ->placeholder(__('Please explain why this reimbursement is being rejected...'))
                        ->required()
                        ->rows(3)
                        ->maxLength(1000),
                ])
                ->visible(fn (): bool => $this->canApproveWithWorkflow())
                ->action(fn (array $data) => $this->handleWorkflowReject($data)),

            // Legacy approval action (for records without workflow)
            Actions\Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->canApproveLegacy())
                ->requiresConfirmation()
                ->action(function () {
                    app(ReimbursementService::class)->approve($this->record);
                    Notification::make()->title('Reimbursement Approved')->success()->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            // Legacy rejection action (for records without workflow)
            Actions\Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->canApproveLegacy())
                ->form([
                    Forms\Components\Textarea::make('rejection_reason')
                        ->required()
                        ->label('Reason for Rejection'),
                ])
                ->action(function (array $data) {
                    app(ReimbursementService::class)->reject($this->record, $data['rejection_reason']);
                    Notification::make()->title('Reimbursement Rejected')->success()->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                }),

            Actions\Action::make('markAsPaid')
                ->label('Mark as Paid')
                ->icon('heroicon-o-banknotes')
                ->color('info')
                ->visible(fn () => $this->record->canMarkAsPaid())
                ->requiresConfirmation()
                ->action(function () {
                    app(ReimbursementService::class)->markAsPaid($this->record, auth()->user());
                    Notification::make()->title('Reimbursement Marked as Paid')->success()->send();
                    $this->redirect($this->getResource()::getUrl('index'));
                }),
        ];
    }

    /**
     * Check if the current user can approve the record using workflow.
     */
    protected function canApproveWithWorkflow(): bool
    {
        $record = $this->record;

        // Only show workflow actions for pending records with approval workflow
        if (!$record->isPending() || !$record->hasApprovalWorkflow()) {
            return false;
        }

        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return $record->canBeApprovedBy($user);
    }

    /**
     * Check if the current user can approve the record using legacy method.
     */
    protected function canApproveLegacy(): bool
    {
        $record = $this->record;

        // Only show legacy actions for pending records without approval workflow
        if (!$record->canApprove() || $record->hasApprovalWorkflow()) {
            return false;
        }

        return true;
    }

    /**
     * Handle workflow-based approval.
     */
    protected function handleWorkflowApprove(array $data): void
    {
        $user = auth()->user();
        $currentStep = $this->record->currentApprovalStep();

        if (!$currentStep) {
            Notification::make()
                ->title(__('Error'))
                ->body(__('No pending approval step found.'))
                ->danger()
                ->send();
            return;
        }

        try {
            $service = app(ApprovalWorkflowService::class);
            $service->approve($currentStep, $user, $data['notes'] ?? null);

            Notification::make()
                ->title(__('Reimbursement Approved'))
                ->body(__('The reimbursement has been approved successfully.'))
                ->success()
                ->send();

            $this->redirect($this->getResource()::getUrl('index'));
        } catch (\Exception $e) {
            Notification::make()
                ->title(__('Approval Failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Handle workflow-based rejection.
     */
    protected function handleWorkflowReject(array $data): void
    {
        $user = auth()->user();
        $currentStep = $this->record->currentApprovalStep();

        if (!$currentStep) {
            Notification::make()
                ->title(__('Error'))
                ->body(__('No pending approval step found.'))
                ->danger()
                ->send();
            return;
        }

        try {
            $service = app(ApprovalWorkflowService::class);
            $service->reject($currentStep, $user, $data['reason']);

            Notification::make()
                ->title(__('Reimbursement Rejected'))
                ->body(__('The reimbursement has been rejected.'))
                ->success()
                ->send();

            $this->redirect($this->getResource()::getUrl('index'));
        } catch (\Exception $e) {
            Notification::make()
                ->title(__('Rejection Failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
