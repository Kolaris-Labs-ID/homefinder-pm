<?php

namespace App\Filament\Resources\WeeklyAllowanceBatchResource\Pages;

use App\Exports\WeeklyAllowanceExport;
use App\Filament\Resources\WeeklyAllowanceBatchResource;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Services\Hris\PayrollApprovalService;
use Filament\Actions;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Maatwebsite\Excel\Facades\Excel;

class ViewWeeklyAllowanceBatch extends ViewRecord
{
    protected static string $resource = WeeklyAllowanceBatchResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Batch Information')
                    ->schema([
                        TextEntry::make('week_label')
                            ->label('Week Period'),
                        TextEntry::make('payment_date')
                            ->date(),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => WeeklyAllowanceBatch::getStatusColors()[$state] ?? 'gray')
                            ->description(fn (WeeklyAllowanceBatch $record) => $record->current_approver_name ? "Waiting: {$record->current_approver_name}" : null),
                    ])->columns(3),

                Section::make('Summary')
                    ->schema([
                        TextEntry::make('total_employees')
                            ->label('Total Employees'),
                        TextEntry::make('employees_with_warnings')
                            ->label('Employees with Warnings')
                            ->color('danger'),
                        TextEntry::make('total_amount')
                            ->label('Total Amount')
                            ->money('IDR'),
                    ])->columns(3),

                Section::make('Rejection Details')
                    ->icon('heroicon-o-x-circle')
                    ->iconColor('danger')
                    ->schema([
                        TextEntry::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->columnSpanFull(),
                        TextEntry::make('rejectedByApprover')
                            ->label('Rejected By')
                            ->state(function (WeeklyAllowanceBatch $record): ?string {
                                $rejectionLog = $record->approvalLogs()
                                    ->where('action', 'rejected')
                                    ->latest('created_at')
                                    ->with('actedBy')
                                    ->first();

                                return $rejectionLog?->actedBy?->name;
                            }),
                        TextEntry::make('rejectedAt')
                            ->label('Rejected At')
                            ->state(function (WeeklyAllowanceBatch $record): ?string {
                                $rejectionLog = $record->approvalLogs()
                                    ->where('action', 'rejected')
                                    ->latest('created_at')
                                    ->first();

                                return $rejectionLog?->created_at?->format('d M Y H:i');
                            }),
                    ])
                    ->columns(3)
                    ->visible(fn (WeeklyAllowanceBatch $record): bool => $record->status === 'rejected' && ! empty($record->rejection_reason)),

                Section::make('Notes')
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Notes')
                            ->default('-'),
                    ]),

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
            // Submit for Approval (new workflow)
            Actions\Action::make('submitForApproval')
                ->label('Submit for Approval')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->visible(fn () => $this->record->canSubmitForApproval())
                ->requiresConfirmation()
                ->modalHeading('Submit for Approval')
                ->modalDescription('Are you sure you want to submit this weekly allowance batch for approval?')
                ->action(function () {
                    $service = app(PayrollApprovalService::class);

                    try {
                        $service->submitForApproval($this->record, auth()->user());

                        Notification::make()
                            ->title('Submitted for Approval')
                            ->body('The weekly allowance batch has been submitted for finance approval.')
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
                ->modalHeading('Approve Weekly Allowance Batch')
                ->modalDescription('Are you sure you want to approve this weekly allowance batch and pass it to the next approver?')
                ->action(function () {
                    $service = app(PayrollApprovalService::class);

                    try {
                        $service->approve($this->record, auth()->user());

                        Notification::make()
                            ->title('Batch Approved')
                            ->body('The weekly allowance batch has been approved and moved to the next step.')
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
                ->modalDescription('This is the final approval step. Are you sure you want to approve this weekly allowance batch and mark it as paid?')
                ->action(function () {
                    $service = app(PayrollApprovalService::class);

                    try {
                        $service->approveAndPay($this->record, auth()->user());

                        Notification::make()
                            ->title('Approved & Paid')
                            ->body('The weekly allowance batch has been approved and marked as paid.')
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
                ->modalHeading('Reject Weekly Allowance Batch')
                ->modalDescription('Please provide a reason for rejecting this weekly allowance batch.')
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
                            ->body('The weekly allowance batch has been rejected.')
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
                ->modalDescription('Are you sure you want to resubmit this weekly allowance batch for approval?')
                ->action(function () {
                    $service = app(PayrollApprovalService::class);

                    try {
                        $service->resubmit($this->record, auth()->user());

                        Notification::make()
                            ->title('Resubmitted for Approval')
                            ->body('The weekly allowance batch has been resubmitted for finance approval.')
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

            // Export CSV for bank transfer
            Actions\Action::make('export')
                ->label('Export Bank Transfer')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn () => in_array($this->record->status, ['paid']))
                ->action(function () {
                    $weekLabel = $this->record->week_start_date->format('d') . '-' .
                        $this->record->week_end_date->format('d_M_Y');
                    $filename = "weekly_allowance_{$weekLabel}.xlsx";

                    return Excel::download(
                        new WeeklyAllowanceExport($this->record),
                        $filename
                    );
                }),

            Actions\EditAction::make()
                ->visible(fn () => $this->record->isEditable()),
        ];
    }
}
