<?php

namespace App\Filament\Resources\LeaveRequestResource\Pages;

use App\Filament\Resources\LeaveRequestResource;
use App\Models\Hris\LeaveRequest;
use App\Services\Hris\ApprovalWorkflowService;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists\Components\Livewire;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;

class ViewLeaveRequest extends ViewRecord
{
    protected static string $resource = LeaveRequestResource::class;

    /**
     * Get the infolist schema for the view page: leave details (incl. the doctor letter
     * attachment) plus the ApprovalHistory component when the record has an approval workflow.
     */
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Leave Details')
                    ->schema([
                        TextEntry::make('employee.user.name')->label('Employee'),
                        TextEntry::make('leaveType.name')->label('Leave Type'),
                        TextEntry::make('start_date')->date(),
                        TextEntry::make('end_date')->date(),
                        TextEntry::make('total_days')->suffix(' days'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('reason')->columnSpanFull()->placeholder('—'),
                        TextEntry::make('attachment_path')
                            ->label("Doctor's letter / attachment")
                            ->state('View attachment')
                            ->badge()
                            ->icon('heroicon-o-paper-clip')
                            ->url(fn (LeaveRequest $record): string => route('leave-requests.attachment.view', $record), shouldOpenInNewTab: true)
                            ->visible(fn (LeaveRequest $record): bool => filled($record->attachment_path))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                // Medical certificate tracking — only shown for provisional Sick Leave approvals.
                Section::make('Medical Certificate')
                    ->schema([
                        TextEntry::make('certificate_status')->badge(),
                        TextEntry::make('certificate_deadline_at')->dateTime()->placeholder('—'),
                        TextEntry::make('certificate_submitted_at')->dateTime()->placeholder('—'),
                        TextEntry::make('certificate_verified_at')->dateTime()->placeholder('—'),
                        TextEntry::make('certificateVerifiedBy.name')->label('Reviewed By')->placeholder('—'),
                        TextEntry::make('certificate_rejection_reason')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('certificate_resolution')->badge()->placeholder('—'),
                        TextEntry::make('certificate_resolved_at')->dateTime()->placeholder('—'),
                        TextEntry::make('certificateResolvedBy.name')->label('Resolved By')->placeholder('—'),
                        TextEntry::make('convertedFrom.id')
                            ->label('Converted From Sick Leave #')
                            ->placeholder('—'),
                    ])
                    ->columns(2)
                    ->visible(fn (LeaveRequest $record): bool => filled($record->certificate_status) || filled($record->converted_from_leave_request_id)),

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

            // Medical certificate review (provisional Sick Leave approval)
            Actions\Action::make('verify_certificate')
                ->label(__('Verify Certificate'))
                ->icon('heroicon-o-document-check')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->canReviewCertificate())
                ->action(fn () => $this->handleVerifyCertificate()),
            Actions\Action::make('reject_certificate')
                ->label(__('Reject Certificate'))
                ->icon('heroicon-o-document-minus')
                ->color('danger')
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label(__('Rejection Reason'))
                        ->required()
                        ->rows(3)
                        ->maxLength(1000),
                ])
                ->visible(fn (): bool => $this->canReviewCertificate())
                ->action(fn (array $data) => $this->handleRejectCertificate($data['reason'])),

            // Overdue-certificate resolution: convert to Annual Leave / Alpha
            Actions\Action::make('resolve_certificate')
                ->label(__('Resolve (Convert/Alpha)'))
                ->icon('heroicon-o-scale')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(__('Resolve Overdue Sick Leave'))
                ->modalDescription(fn (): string => LeaveRequestResource::certificateResolutionPreview($this->record))
                ->visible(fn (): bool => $this->canResolveCertificate())
                ->action(fn () => $this->handleResolveCertificate()),
        ];
    }

    protected function canReviewCertificate(): bool
    {
        return $this->record->certificate_status === LeaveRequest::CERT_STATUS_SUBMITTED
            && Gate::allows('update', $this->record);
    }

    protected function handleVerifyCertificate(): void
    {
        try {
            LeaveRequestActionHandler::verifyCertificate($this->record, auth()->user());

            Notification::make()->title(__('Certificate Verified'))->success()->send();
        } catch (\Exception $e) {
            Notification::make()->title(__('Verification Failed'))->body($e->getMessage())->danger()->send();
        }
    }

    protected function handleRejectCertificate(string $reason): void
    {
        try {
            LeaveRequestActionHandler::rejectCertificate($this->record, auth()->user(), $reason);

            Notification::make()->title(__('Certificate Rejected'))->success()->send();
        } catch (\Exception $e) {
            Notification::make()->title(__('Rejection Failed'))->body($e->getMessage())->danger()->send();
        }
    }

    protected function canResolveCertificate(): bool
    {
        return $this->record->certificate_status === LeaveRequest::CERT_STATUS_OVERDUE
            && $this->record->status === LeaveRequest::STATUS_APPROVED
            && Gate::allows('update', $this->record);
    }

    protected function handleResolveCertificate(): void
    {
        try {
            $result = LeaveRequestActionHandler::resolveCertificate($this->record, auth()->user());

            Notification::make()
                ->title(__('Sick Leave Resolved'))
                ->body(__(':converted day(s) converted to Annual Leave, :alpha day(s) marked Alpha.', [
                    'converted' => $result['converted_days'],
                    'alpha' => $result['alpha_days'],
                ]))
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title(__('Resolution Failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Check if the current user can approve the record using workflow.
     */
    protected function canApproveWithWorkflow(): bool
    {
        $record = $this->record;

        // Only show workflow actions for pending records with approval workflow
        if (! $record->isPending() || ! $record->hasApprovalWorkflow()) {
            return false;
        }

        $user = auth()->user();
        if (! $user) {
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
        if (! $record->isPending() || $record->hasApprovalWorkflow()) {
            return false;
        }

        return Gate::allows('approve', $record);
    }

    // TODO: workflow_approve/workflow_reject actions and handleWorkflowApprove/handleWorkflowReject
    // are near-identical twins — collapse into one handleWorkflowDecision(string $action, array $data) helper.

    /**
     * Handle workflow-based approval.
     */
    protected function handleWorkflowApprove(array $data): void
    {
        $user = auth()->user();
        $step = $this->record->pendingStepFor($user);

        if (! $step) {
            Notification::make()
                ->title(__('Error'))
                ->body(__('No pending approval step found for your account.'))
                ->danger()
                ->send();

            return;
        }

        try {
            $service = app(ApprovalWorkflowService::class);
            $service->approve($step, $user, $data['notes'] ?? null);

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
        $step = $this->record->pendingStepFor($user);

        if (! $step) {
            Notification::make()
                ->title(__('Error'))
                ->body(__('No pending approval step found for your account.'))
                ->danger()
                ->send();

            return;
        }

        try {
            $service = app(ApprovalWorkflowService::class);
            $service->reject($step, $user, $data['reason']);

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
}
