<?php

namespace App\Filament\Actions;

use App\Contracts\Hris\ApprovableContract;
use App\Services\Hris\ApprovalWorkflowService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament action for approving approvable items.
 *
 * This action provides a modal with an optional notes field
 * and calls ApprovalWorkflowService::approve to process the approval.
 *
 * Requirements: 1.1, 1.3
 */
class ApproveAction
{
    /**
     * Create the approve action for use in Filament resources.
     */
    public static function make(string $name = 'approve'): Action
    {
        return Action::make($name)
            ->label(__('Approve'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->modalHeading(__('Approve Request'))
            ->modalDescription(__('Are you sure you want to approve this request? You can optionally add notes.'))
            ->modalSubmitActionLabel(__('Approve'))
            ->form([
                Textarea::make('notes')
                    ->label(__('Notes (Optional)'))
                    ->placeholder(__('Add any notes or comments for this approval...'))
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->visible(fn (Model $record): bool => static::canApprove($record))
            ->action(fn (Model $record, array $data) => static::handle($record, $data))
            ->successNotificationTitle(__('Request Approved'))
            ->requiresConfirmation(false); // We use form modal instead
    }

    /**
     * Check if the current user can approve the record.
     */
    protected static function canApprove(Model $record): bool
    {
        // Check if record implements ApprovableContract
        if (!$record instanceof ApprovableContract) {
            return false;
        }

        // Check if record is pending
        if (!$record->isPending()) {
            return false;
        }

        // Check if current user can approve
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return $record->canBeApprovedBy($user);
    }

    /**
     * Handle the approval action.
     */
    protected static function handle(Model $record, array $data): void
    {
        if (!$record instanceof ApprovableContract) {
            Notification::make()
                ->title(__('Error'))
                ->body(__('This record cannot be approved.'))
                ->danger()
                ->send();
            return;
        }

        $user = auth()->user();
        $currentStep = $record->currentApprovalStep();

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
                ->title(__('Request Approved'))
                ->body(__('The request has been approved successfully.'))
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title(__('Approval Failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
