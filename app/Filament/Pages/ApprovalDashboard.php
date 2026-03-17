<?php

namespace App\Filament\Pages;

use App\Models\Hris\ApprovalStep;
use App\Models\Hris\ApprovalWorkflow;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\Reimbursement;
use App\Models\Hris\TrainingRecord;
use App\Services\Hris\ApprovalWorkflowService;
use App\Services\Hris\PgpService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class ApprovalDashboard extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Approval Dashboard';
    protected static ?string $title = 'Approval Dashboard';
    protected static ?int $navigationSort = 5;
    protected static string $view = 'filament.pages.approval-dashboard';
    protected static ?string $navigationGroup = 'HRIS';
    protected static ?string $slug = 'approval-dashboard';

    public ?string $moduleFilter = null;

    protected ApprovalWorkflowService $approvalService;
    protected PgpService $pgpService;

    public function boot(ApprovalWorkflowService $approvalService, PgpService $pgpService): void
    {
        $this->approvalService = $approvalService;
        $this->pgpService = $pgpService;
    }

    public function mount(): void
    {
        $this->moduleFilter = null;
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        // Allow access if user has any approval-related permission or is super admin
        return $user->hasAnyPermission([
            'view_any_leave::request',
            'view_any_reimbursement',
            'view_any_payroll::record',
            'view_any_training::record',
        ]) || $user->hasRole('super_admin');
    }


    public function getSubheading(): ?string
    {
        return 'Manage pending approval requests';
    }

    /**
     * Get pending approvals for the current user.
     */
    public function getPendingApprovals(): Collection
    {
        $user = Auth::user();
        if (!$user) {
            return new Collection();
        }

        if ($this->moduleFilter) {
            return $this->approvalService->getPendingApprovalsByModule($user, $this->moduleFilter);
        }

        return $this->approvalService->getPendingApprovalsForUser($user);
    }

    /**
     * Get approval statistics for the current user.
     */
    public function getApprovalStatistics(): array
    {
        $user = Auth::user();
        if (!$user) {
            return ['pending' => 0, 'approved' => 0, 'rejected' => 0];
        }

        return $this->approvalService->getApprovalStatistics($user);
    }

    /**
     * Get the pending count for navigation badge.
     */
    public static function getNavigationBadge(): ?string
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        $service = app(ApprovalWorkflowService::class);
        $stats = $service->getApprovalStatistics($user);

        return $stats['pending'] > 0 ? (string) $stats['pending'] : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('approvable_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => $this->getModuleLabel($state))
                    ->badge()
                    ->color(fn (string $state): string => $this->getModuleColor($state)),
                TextColumn::make('approvable.employee.user.name')
                    ->label('Requestor')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('step_order')
                    ->label('Step')
                    ->sortable(),
                TextColumn::make('approver_role')
                    ->label('Required Role')
                    ->placeholder('Direct Assignment'),
                TextColumn::make('created_at')
                    ->label('Request Date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('module')
                    ->label('Module')
                    ->options([
                        ApprovalWorkflow::MODULE_LEAVE => 'Leave Requests',
                        ApprovalWorkflow::MODULE_REIMBURSEMENT => 'Reimbursements',
                        ApprovalWorkflow::MODULE_PAYROLL_ADJUSTMENT => 'Payroll Adjustments',
                        ApprovalWorkflow::MODULE_TRAINING => 'Training Records',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!$data['value']) {
                            return $query;
                        }

                        $moduleToClass = [
                            ApprovalWorkflow::MODULE_LEAVE => LeaveRequest::class,
                            ApprovalWorkflow::MODULE_REIMBURSEMENT => Reimbursement::class,
                            ApprovalWorkflow::MODULE_PAYROLL_ADJUSTMENT => \App\Models\Hris\PayrollRecord::class,
                            ApprovalWorkflow::MODULE_TRAINING => TrainingRecord::class,
                        ];

                        $approvableClass = $moduleToClass[$data['value']] ?? null;

                        if ($approvableClass) {
                            return $query->where('approvable_type', $approvableClass);
                        }

                        return $query;
                    }),
            ])
            ->actions([
                TableAction::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form(function (ApprovalStep $record): array {
                        $formFields = [
                            Textarea::make('notes')
                                ->label('Notes (Optional)')
                                ->placeholder('Add any notes for this approval...'),
                        ];

                        // Add PGP fields for training record approvals
                        if ($record->approvable_type === TrainingRecord::class) {
                            $trainingRecord = $record->approvable;

                            if ($trainingRecord && $trainingRecord->isPaidTraining()) {
                                // Paid training: show PGP input field
                                $formFields[] = TextInput::make('pgp_points')
                                    ->label('PGP Points')
                                    ->numeric()
                                    ->required()
                                    ->minValue(TrainingRecord::PGP_PAID_MIN)
                                    ->maxValue(TrainingRecord::PGP_PAID_MAX)
                                    ->default(20)
                                    ->helperText('Enter PGP points between ' . TrainingRecord::PGP_PAID_MIN . ' and ' . TrainingRecord::PGP_PAID_MAX . ' for paid training');
                            } else {
                                // Free training: show info placeholder
                                $formFields[] = Placeholder::make('pgp_info')
                                    ->label('PGP Points')
                                    ->content('35 PGP points will be automatically assigned for free training.');
                            }
                        }

                        return $formFields;
                    })
                    ->action(function (ApprovalStep $record, array $data): void {
                        $this->approveStep($record, $data['notes'] ?? null, $data['pgp_points'] ?? null);
                    })
                    ->visible(fn (ApprovalStep $record): bool => $record->status === ApprovalStep::STATUS_PENDING),
                TableAction::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->placeholder('Please provide a reason for rejection...'),
                    ])
                    ->action(function (ApprovalStep $record, array $data): void {
                        $this->rejectStep($record, $data['reason']);
                    })
                    ->visible(fn (ApprovalStep $record): bool => $record->status === ApprovalStep::STATUS_PENDING),
                TableAction::make('view')
                    ->label('View Details')
                    ->icon('heroicon-o-eye')
                    ->url(fn (ApprovalStep $record): string => $this->getApprovableUrl($record)),
            ])
            ->bulkActions([
                BulkAction::make('bulkApprove')
                    ->label('Approve Selected')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('notes')
                            ->label('Notes (Optional)')
                            ->placeholder('Add notes for all selected approvals...'),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $this->bulkApprove($records, $data['notes'] ?? null);
                    })
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('bulkReject')
                    ->label('Reject Selected')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->placeholder('Please provide a reason for rejection...'),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        $this->bulkReject($records, $data['reason']);
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No pending approvals')
            ->emptyStateDescription('You have no items waiting for your approval.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check');
    }


    /**
     * Get the table query for pending approvals.
     */
    protected function getTableQuery(): Builder
    {
        $user = Auth::user();
        if (!$user) {
            return ApprovalStep::query()->whereRaw('1 = 0');
        }

        $query = ApprovalStep::query()
            ->where('status', ApprovalStep::STATUS_PENDING);

        // Super admins see all pending approvals
        if (!$user->hasRole('super_admin')) {
            $userRoles = $user->roles->pluck('name')->toArray();
            $query->where(function ($q) use ($user, $userRoles) {
                $q->where('approver_id', $user->id);
                if (!empty($userRoles)) {
                    $q->orWhereIn('approver_role', $userRoles);
                }
            });
        }

        return $query->with(['approvable.employee.user', 'workflow']);
    }

    /**
     * Approve a single step.
     */
    protected function approveStep(ApprovalStep $step, ?string $notes = null, ?int $pgpPoints = null): void
    {
        try {
            $this->approvalService->approve($step, Auth::user(), $notes);

            // Handle PGP point assignment for training records
            if ($step->approvable_type === TrainingRecord::class) {
                $trainingRecord = $step->approvable;

                if ($trainingRecord) {
                    if ($trainingRecord->isPaidTraining()) {
                        // Paid training: use provided points
                        if ($pgpPoints !== null) {
                            $this->pgpService->awardPoints($trainingRecord, $pgpPoints);
                        }
                    } else {
                        // Free training: auto-assign 35 PGP
                        $this->pgpService->awardPoints($trainingRecord, TrainingRecord::PGP_FREE_TRAINING);
                    }
                }
            }

            Notification::make()
                ->title('Approval Successful')
                ->body('The item has been approved.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Approval Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Reject a single step.
     */
    protected function rejectStep(ApprovalStep $step, string $reason): void
    {
        try {
            $this->approvalService->reject($step, Auth::user(), $reason);

            Notification::make()
                ->title('Rejection Successful')
                ->body('The item has been rejected.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Rejection Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Bulk approve multiple steps.
     */
    protected function bulkApprove(Collection $records, ?string $notes = null): void
    {
        $stepIds = $records->pluck('id')->toArray();
        $result = $this->approvalService->bulkApprove($stepIds, Auth::user(), $notes);

        if ($result['success']) {
            Notification::make()
                ->title('Bulk Approval Successful')
                ->body(count($stepIds) . ' items have been approved.')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Bulk Approval Failed')
                ->body($result['error'] ?? 'An error occurred during bulk approval.')
                ->danger()
                ->send();
        }
    }

    /**
     * Bulk reject multiple steps.
     */
    protected function bulkReject(Collection $records, string $reason): void
    {
        $failedCount = 0;
        $successCount = 0;

        foreach ($records as $step) {
            try {
                $this->approvalService->reject($step, Auth::user(), $reason);
                $successCount++;
            } catch (\Exception $e) {
                $failedCount++;
            }
        }

        if ($failedCount === 0) {
            Notification::make()
                ->title('Bulk Rejection Successful')
                ->body("{$successCount} items have been rejected.")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Bulk Rejection Partially Failed')
                ->body("{$successCount} items rejected, {$failedCount} failed.")
                ->warning()
                ->send();
        }
    }

    /**
     * Get the module label from approvable type.
     */
    public function getModuleLabel(string $approvableType): string
    {
        return match ($approvableType) {
            LeaveRequest::class => 'Leave Request',
            Reimbursement::class => 'Reimbursement',
            \App\Models\Hris\PayrollRecord::class => 'Payroll Adjustment',
            TrainingRecord::class => 'Training Record',
            default => class_basename($approvableType),
        };
    }

    /**
     * Get the module color from approvable type.
     */
    public function getModuleColor(string $approvableType): string
    {
        return match ($approvableType) {
            LeaveRequest::class => 'info',
            Reimbursement::class => 'success',
            \App\Models\Hris\PayrollRecord::class => 'warning',
            TrainingRecord::class => 'primary',
            default => 'gray',
        };
    }

    /**
     * Get the URL to view the approvable item.
     */
    protected function getApprovableUrl(ApprovalStep $step): string
    {
        $approvable = $step->approvable;

        if (!$approvable) {
            return '#';
        }

        return match ($step->approvable_type) {
            LeaveRequest::class => \App\Filament\Resources\LeaveRequestResource::getUrl('view', ['record' => $approvable->id]),
            Reimbursement::class => \App\Filament\Resources\ReimbursementResource::getUrl('edit', ['record' => $approvable->id]),
            \App\Models\Hris\PayrollRecord::class => \App\Filament\Resources\PayrunBatchResource::getUrl('index'),
            TrainingRecord::class => \App\Filament\Resources\TrainingRecordResource::getUrl('edit', ['record' => $approvable->id]),
            default => '#',
        };
    }
}
