<?php

namespace App\Filament\Resources\OvertimePayBatchResource\Pages;

use App\Filament\Resources\OvertimePayBatchResource;
use App\Models\Hris\OvertimePayBatch;
use App\Services\Hris\PayrollApprovalService;
use Filament\Actions;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOvertimePayBatch extends ViewRecord
{
    protected static string $resource = OvertimePayBatchResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Batch Information')
                    ->schema([
                        TextEntry::make('week_label')->label('Week Period'),
                        TextEntry::make('payment_date')->date(),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => OvertimePayBatch::getStatusColors()[$state] ?? 'gray')
                            ->helperText(fn (OvertimePayBatch $record) => $record->current_approver_name
                                ? "Waiting: {$record->current_approver_name}" : null),
                    ])->columns(3),

                Section::make('Summary')
                    ->schema([
                        TextEntry::make('total_employees')->label('Total Employees'),
                        TextEntry::make('employees_with_warnings')
                            ->label('Employees with Warnings')
                            ->color('danger'),
                        TextEntry::make('total_amount')->label('Total Amount')->money('IDR'),
                    ])->columns(3),

                Section::make('Rejection Details')
                    ->icon('heroicon-o-x-circle')
                    ->iconColor('danger')
                    ->schema([
                        TextEntry::make('rejection_reason')->label('Rejection Reason')->columnSpanFull(),
                        TextEntry::make('rejectedBy')
                            ->label('Rejected By')
                            ->state(function (OvertimePayBatch $record): ?string {
                                $log = $record->approvalLogs()
                                    ->where('action', 'rejected')
                                    ->latest('created_at')
                                    ->with('actedBy')
                                    ->first();
                                return $log?->actedBy?->name;
                            }),
                        TextEntry::make('rejectedAt')
                            ->label('Rejected At')
                            ->state(function (OvertimePayBatch $record): ?string {
                                $log = $record->approvalLogs()
                                    ->where('action', 'rejected')
                                    ->latest('created_at')
                                    ->first();
                                return $log?->created_at?->format('d M Y H:i');
                            }),
                    ])
                    ->columns(3)
                    ->visible(fn (OvertimePayBatch $record): bool =>
                        $record->status === 'rejected' && !empty($record->rejection_reason)),

                Section::make('Notes')
                    ->schema([
                        TextEntry::make('notes')->label('Notes')->default('-'),
                    ]),

                Section::make('Approval Progress')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->schema([
                        ViewEntry::make('approval_progress')
                            ->label('')
                            ->view('filament.infolists.entries.approval-progress')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (OvertimePayBatch $record): bool =>
                        in_array($record->status, ['pending_finance', 'paid', 'rejected'])),

                Section::make('Approval Timeline')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        RepeatableEntry::make('approvalLogs')
                            ->label('')
                            ->schema([
                                TextEntry::make('actedBy.name')->label('User'),
                                TextEntry::make('action')
                                    ->label('Action')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'submitted'   => 'info',
                                        'approved'    => 'success',
                                        'paid'        => 'success',
                                        'escalated'   => 'warning',
                                        'rejected'    => 'danger',
                                        'resubmitted' => 'primary',
                                        default       => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                                TextEntry::make('notes')->label('Notes')->default('-'),
                                TextEntry::make('created_at')->label('Timestamp')->dateTime(),
                            ])
                            ->columns(4),
                    ])
                    ->collapsible(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('submitForApproval')
                ->label('Submit for Approval')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->visible(fn () => $this->record->canSubmitForApproval())
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        app(PayrollApprovalService::class)->submitForApproval($this->record, auth()->user());
                        Notification::make()->title('Submitted for Approval')
                            ->body('The overtime pay batch has been submitted for finance approval.')
                            ->success()->send();
                        $this->refreshFormData(['status']);
                    } catch (\Exception $e) {
                        Notification::make()->title('Submission Failed')->body($e->getMessage())->danger()->send();
                    }
                }),

            Actions\Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(function () {
                    if ($this->record->isPaid() || !$this->record->canBeApprovedByFinance()) {
                        return false;
                    }
                    $service = app(PayrollApprovalService::class);
                    return $service->canUserActOnBatch($this->record, auth()->user())
                        && !$service->isFinalFinanceStep($this->record);
                })
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        app(PayrollApprovalService::class)->approve($this->record, auth()->user());
                        Notification::make()->title('Batch Approved')->success()->send();
                        $this->refreshFormData(['status', 'current_finance_step_order']);
                    } catch (\Exception $e) {
                        Notification::make()->title('Approval Failed')->body($e->getMessage())->danger()->send();
                    }
                }),

            Actions\Action::make('approveAndPay')
                ->label('Approve & Pay')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(function () {
                    if ($this->record->isPaid() || !$this->record->canBeApprovedByFinance()) {
                        return false;
                    }
                    $service = app(PayrollApprovalService::class);
                    return $service->canUserActOnBatch($this->record, auth()->user())
                        && $service->isFinalFinanceStep($this->record);
                })
                ->requiresConfirmation()
                ->modalHeading('Approve & Pay')
                ->modalDescription('This is the final approval step. Are you sure you want to approve and mark this overtime pay batch as paid?')
                ->action(function () {
                    try {
                        app(PayrollApprovalService::class)->approveAndPay($this->record, auth()->user());
                        Notification::make()->title('Approved & Paid')->success()->send();
                        $this->refreshFormData(['status', 'current_finance_step_order']);
                    } catch (\Exception $e) {
                        Notification::make()->title('Approval Failed')->body($e->getMessage())->danger()->send();
                    }
                }),

            Actions\Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(function () {
                    if (!$this->record->canBeRejected()) {
                        return false;
                    }
                    return app(PayrollApprovalService::class)->canUserActOnBatch($this->record, auth()->user());
                })
                ->requiresConfirmation()
                ->form([
                    \Filament\Forms\Components\Textarea::make('rejection_reason')
                        ->label('Rejection Reason')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    try {
                        app(PayrollApprovalService::class)->reject($this->record, auth()->user(), $data['rejection_reason']);
                        Notification::make()->title('Batch Rejected')->warning()->send();
                        $this->refreshFormData(['status']);
                    } catch (\Exception $e) {
                        Notification::make()->title('Rejection Failed')->body($e->getMessage())->danger()->send();
                    }
                }),

            Actions\Action::make('resubmit')
                ->label('Resubmit')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->visible(fn () => $this->record->canBeResubmitted())
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        app(PayrollApprovalService::class)->resubmit($this->record, auth()->user());
                        Notification::make()->title('Resubmitted for Approval')->success()->send();
                        $this->refreshFormData(['status']);
                    } catch (\Exception $e) {
                        Notification::make()->title('Resubmission Failed')->body($e->getMessage())->danger()->send();
                    }
                }),

            Actions\EditAction::make()
                ->visible(fn () => $this->record->isEditable()),
        ];
    }
}
