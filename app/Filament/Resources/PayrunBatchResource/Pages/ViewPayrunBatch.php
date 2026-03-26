<?php

namespace App\Filament\Resources\PayrunBatchResource\Pages;

use App\Filament\Resources\PayrunBatchResource;
use App\Models\Hris\PayrunBatch;
use App\Services\Hris\PayrollApprovalService;
use Filament\Actions;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPayrunBatch extends ViewRecord
{
    protected static string $resource = PayrunBatchResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Batch Information')
                    ->schema([
                        TextEntry::make('period_label')
                            ->label('Period'),
                        TextEntry::make('pay_date')
                            ->date(),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'draft' => 'gray',
                                'calculated' => 'info',
                                'pending_finance' => 'warning',
                                'rejected' => 'danger',
                                'paid' => 'success',
                                default => 'gray',
                            })
                            ->helperText(fn (PayrunBatch $record) => $record->current_approver_name ? "Waiting: {$record->current_approver_name}" : null),
                    ])->columns(3),

                Section::make('Payroll Summary')
                    ->schema([
                        TextEntry::make('employee_count')
                            ->label('Total Employees'),
                        TextEntry::make('total_net_income')
                            ->label('Total Net Income')
                            ->money('IDR'),
                    ])->columns(2),

                Section::make('Rejection Details')
                    ->icon('heroicon-o-x-circle')
                    ->iconColor('danger')
                    ->schema([
                        TextEntry::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->columnSpanFull(),
                        TextEntry::make('rejectedByApprover')
                            ->label('Rejected By')
                            ->state(function (PayrunBatch $record): ?string {
                                $rejectionLog = $record->approvalLogs()
                                    ->where('action', 'rejected')
                                    ->latest('created_at')
                                    ->with('actedBy')
                                    ->first();

                                return $rejectionLog?->actedBy?->name;
                            }),
                        TextEntry::make('rejectedAt')
                            ->label('Rejected At')
                            ->state(function (PayrunBatch $record): ?string {
                                $rejectionLog = $record->approvalLogs()
                                    ->where('action', 'rejected')
                                    ->latest('created_at')
                                    ->first();

                                return $rejectionLog?->created_at?->format('d M Y H:i');
                            }),
                    ])
                    ->columns(3)
                    ->visible(fn (PayrunBatch $record): bool => $record->status === 'rejected' && ! empty($record->rejection_reason)),

                Section::make('Notes')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Notes')
                            ->default('-'),
                    ]),

                Section::make('Approval Progress')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->description('Sequential approval chain. Each approver must review the payroll records before approving.')
                    ->schema([
                        ViewEntry::make('approval_progress')
                            ->label('')
                            ->view('filament.infolists.entries.approval-progress')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (PayrunBatch $record): bool => in_array($record->status, ['pending_finance', 'paid', 'rejected'])),

                Section::make('Approval Timeline')
                    ->icon('heroicon-o-clock')
                    ->description('Chronological history of all approval actions on this batch.')
                    ->schema([
                        RepeatableEntry::make('approvalLogs')
                            ->label('')
                            ->schema([
                                TextEntry::make('actedBy.name')
                                    ->label('User'),
                                TextEntry::make('action')
                                    ->label('Action')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'submitted' => 'info',
                                        'approved' => 'success',
                                        'paid' => 'success',
                                        'escalated' => 'warning',
                                        'rejected' => 'danger',
                                        'resubmitted' => 'primary',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                                TextEntry::make('notes')
                                    ->label('Notes')
                                    ->default('-')
                                    ->placeholder('-'),
                                TextEntry::make('created_at')
                                    ->label('Timestamp')
                                    ->dateTime(),
                            ])
                            ->columns(4),
                    ])
                    ->collapsible(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            // Submit for Approval
            Actions\Action::make('submitForApproval')
                ->label('Submit for Approval')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->visible(fn () => $this->record->canSubmitForApproval())
                ->requiresConfirmation()
                ->modalHeading('Submit for Approval')
                ->modalDescription('Are you sure you want to submit this payrun batch for approval?')
                ->action(function () {
                    $service = app(PayrollApprovalService::class);

                    try {
                        $service->submitForApproval($this->record, auth()->user());

                        Notification::make()
                            ->title('Submitted for Approval')
                            ->body('The payrun batch has been submitted for finance approval.')
                            ->success()
                            ->send();

                        $this->refreshFormData(['status']);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Submission Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            // Approve (Intermediate Step)
            Actions\Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(function () {
                    if ($this->record->isPaid() || ! $this->record->canBeApprovedByFinance()) {
                        return false;
                    }
                    $service = app(PayrollApprovalService::class);
                    return $service->canUserActOnBatch($this->record, auth()->user()) && !$service->isFinalFinanceStep($this->record);
                })
                ->requiresConfirmation()
                ->modalHeading('Approve Payrun Batch')
                ->modalDescription('Are you sure you want to approve this payrun batch and pass it to the next approver?')
                ->action(function () {
                    $service = app(PayrollApprovalService::class);

                    try {
                        $service->approve($this->record, auth()->user());

                        Notification::make()
                            ->title('Batch Approved')
                            ->body('The payrun batch has been approved and moved to the next step.')
                            ->success()
                            ->send();

                        $this->refreshFormData(['status', 'current_finance_step_order']);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Approval Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            // Approve & Pay (Final Step)
            Actions\Action::make('approveAndPay')
                ->label('Approve & Pay')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(function () {
                    if ($this->record->isPaid() || ! $this->record->canBeApprovedByFinance()) {
                        return false;
                    }
                    $service = app(PayrollApprovalService::class);
                    return $service->canUserActOnBatch($this->record, auth()->user()) && $service->isFinalFinanceStep($this->record);
                })
                ->requiresConfirmation()
                ->modalHeading('Approve & Pay')
                ->modalDescription('This is the final approval step. Are you sure you want to approve this payrun batch and mark it as paid?')
                ->action(function () {
                    $service = app(PayrollApprovalService::class);

                    try {
                        $service->approveAndPay($this->record, auth()->user());

                        Notification::make()
                            ->title('Approved & Paid')
                            ->body('The payrun batch has been approved and marked as paid.')
                            ->success()
                            ->send();

                        $this->refreshFormData(['status', 'current_finance_step_order']);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Approval Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            // Reject
            Actions\Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(function () {
                    if (! $this->record->canBeRejected()) {
                        return false;
                    }
                    $service = app(PayrollApprovalService::class);
                    return $service->canUserActOnBatch($this->record, auth()->user());
                })
                ->requiresConfirmation()
                ->modalHeading('Reject Payrun Batch')
                ->modalDescription('Please provide a reason for rejecting this payrun batch.')
                ->form([
                    \Filament\Forms\Components\Textarea::make('rejection_reason')
                        ->label('Rejection Reason')
                        ->required()
                        ->placeholder('Enter the reason for rejection...')
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $service = app(PayrollApprovalService::class);

                    try {
                        $service->reject($this->record, auth()->user(), $data['rejection_reason']);

                        Notification::make()
                            ->title('Batch Rejected')
                            ->body('The payrun batch has been rejected.')
                            ->warning()
                            ->send();

                        $this->refreshFormData(['status']);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Rejection Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            // Resubmit
            Actions\Action::make('resubmit')
                ->label('Resubmit')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->visible(fn () => $this->record->canBeResubmitted())
                ->requiresConfirmation()
                ->modalHeading('Resubmit for Approval')
                ->modalDescription('Are you sure you want to resubmit this payrun batch for approval?')
                ->action(function () {
                    $service = app(PayrollApprovalService::class);

                    try {
                        $service->resubmit($this->record, auth()->user());

                        Notification::make()
                            ->title('Resubmitted for Approval')
                            ->body('The payrun batch has been resubmitted for finance approval.')
                            ->success()
                            ->send();

                        $this->refreshFormData(['status']);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Resubmission Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\EditAction::make()
                ->visible(fn () => $this->record->isEditable()),
        ];
    }
}
