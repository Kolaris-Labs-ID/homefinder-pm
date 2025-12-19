<?php

namespace App\Filament\Actions;

use App\Contracts\Hris\ApprovableContract;
use App\Services\Hris\ApprovalWorkflowService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament action for rejecting approvable items.
 *
 * This action provides a modal with a required reason field
 * and calls ApprovalWorkflowService::reject to process the rejection.
 *
 * Requirements: 1.1, 1.3
 */
class RejectAction
{
    /**
     * Create the reject action for use in Filament resources.
     */
    public static function make(string $name = 'reject'): Action
    {
        return Action::make($name)
            ->label(__('Reject'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->modalHeading(__('Reject Request'))
            ->modalDescription(__('Please provide a reason for rejecting this request.'))
            ->modalSubmitActionLabel(__('Reject'))
            ->form([
                Textarea::make('reason')
                    ->label(__('Rejection Reason'))
                    ->placeholder(__('Please explain why this request is being rejected...'))
                    ->required()
                    ->rows(3)
                    ->maxLength(1000),
            ])
            ->visible(fn (Model $record): bool => static::canReject($record))
            ->action(fn (Model $record, array $data) => static::handle($record, $data))
            ->successNotificationTitle(__('Request Rejected'))
            ->requiresConfirmation(false); // We use form modal instead
    }

    /**
     * Check if the current user can reject the record.
     */
    protected static function canReject(Model $record): bool
    {
        // Check if record implements ApprovableContract
        if (!$record instanceof ApprovableContract) {
            return false;
        }

        // Check if record is pending
        if (!$record->isPending()) {
            return false;
        }

        // Check if current user can approve (same authorization as approve)
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        return $record->canBeApprovedBy($user);
    }

    /**
     * Handle the rejection action.
     */
    protected static function handle(Model $record, array $data): void
    {
        if (!$record instanceof ApprovableContract) {
            Notification::make()
                ->title(__('Error'))
                ->body(__('This record cannot be rejected.'))
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
            $service->reject($currentStep, $user, $data['reason']);

            Notification::make()
                ->title(__('Request Rejected'))
                ->body(__('The request has been rejected.'))
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title(__('Rejection Failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
