<?php

namespace App\Filament\Resources\LeaveRequestResource\Pages;

use App\Filament\Resources\LeaveRequestResource;
use App\Models\Hris\LeaveRequest;
use App\Models\User;
use App\Services\Hris\ApprovalWorkflowService;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\Livewire;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;

class ViewLeaveRequest extends ViewRecord
{
    protected static string $resource = LeaveRequestResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $leaveRequest = $this->record->loadMissing('employee.user');
        $employeeUserId = $leaveRequest->employee?->user_id;
        $collisionUserId = User::query()->whereKey($leaveRequest->employee_id)->value('id');
        $authUser = request()->user();

        // #region agent log
        $this->debugLog('H3', 'view mount resolved leave request', [
            'record_id' => $leaveRequest->id,
            'route_record' => $record,
            'record_employee_id' => $leaveRequest->employee_id,
            'record_employee_user_id' => $employeeUserId,
            'auth_user_id' => $authUser?->id,
            'auth_employee_id' => data_get($authUser, 'employee.id'),
            'user_exists_with_employee_id' => (bool) $collisionUserId,
        ]);
        // #endregion
    }

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
                ->visible(fn (): bool => Gate::allows('update', $this->record)),

            // Workflow-based approval action
            Actions\Action::make('workflow_approve')
                ->label(__('Approve'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->modalHeading(__('Approve Leave Request'))
                ->modalDescription(__('Are you sure you want to approve this leave request?'))
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
                ->modalHeading(__('Reject Leave Request'))
                ->modalDescription(__('Please provide a reason for rejecting this request.'))
                ->modalSubmitActionLabel(__('Reject'))
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label(__('Rejection Reason'))
                        ->placeholder(__('Please explain why this request is being rejected...'))
                        ->required()
                        ->rows(3)
                        ->maxLength(1000),
                ])
                ->visible(fn (): bool => $this->canApproveWithWorkflow())
                ->action(fn (array $data) => $this->handleWorkflowReject($data)),

            // Legacy approval action (for records without workflow)
            Actions\Action::make('approve')
                ->icon('heroicon-o-check')
                ->requiresConfirmation()
                ->color('success')
                ->visible(fn (): bool => $this->canApproveLegacy())
                ->action(fn () => LeaveRequestActionHandler::approve($this->record)),

            // Legacy rejection action (for records without workflow)
            Actions\Action::make('reject')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->canApproveLegacy())
                ->form([
                    Forms\Components\Textarea::make('rejection_reason')
                        ->required()
                        ->columnSpanFull(),
                ])
                ->action(fn (array $data) => LeaveRequestActionHandler::reject($this->record, $data['rejection_reason'])),

            Actions\Action::make('cancel')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (): bool => Gate::allows('cancel', $this->record))
                ->action(fn () => LeaveRequestActionHandler::cancel($this->record)),
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
        if (!$record->isPending() || $record->hasApprovalWorkflow()) {
            return false;
        }

        return Gate::allows('approve', $record);
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
            $service->approveParallel($currentStep, $user, $data['notes'] ?? null);

            Notification::make()
                ->title(__('Leave Request Approved'))
                ->body(__('The leave request has been approved successfully.'))
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
                ->title(__('Leave Request Rejected'))
                ->body(__('The leave request has been rejected.'))
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

    private function debugLog(string $hypothesisId, string $message, array $data): void
    {
        @file_put_contents(
            '/Users/arirudiana/Devs/hris-phi/.cursor/debug-21491e.log',
            json_encode([
                'sessionId' => '21491e',
                'runId' => 'initial',
                'hypothesisId' => $hypothesisId,
                'location' => 'ViewLeaveRequest.php',
                'message' => $message,
                'data' => $data,
                'timestamp' => round(microtime(true) * 1000),
            ], JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND
        );
    }
}
