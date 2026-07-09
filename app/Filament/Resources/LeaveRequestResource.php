<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeaveRequestResource\Pages;
use App\Models\Hris\LeaveRequest;
use App\Models\Hris\LeaveType;
use App\Models\Hris\PayrunBatch;
use App\Services\Hris\ApprovalWorkflowService;
use App\Services\Hris\LeaveBalanceService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class LeaveRequestResource extends Resource
{
    protected static ?string $model = LeaveRequest::class;

    protected static ?string $navigationGroup = 'HRIS';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Leave Requests';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Leave Details')
                    ->schema([
                        Forms\Components\Select::make('employee_id')
                            ->relationship('employee.user', 'name')
                            ->label('Employee')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn (?LeaveRequest $record) => filled($record)),
                        Forms\Components\Select::make('leave_type_id')
                            ->relationship('leaveType', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\DatePicker::make('start_date')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn (?string $state, callable $set, callable $get) => static::syncTotalDays($set, $get('start_date'), $get('end_date'))),
                        Forms\Components\DatePicker::make('end_date')
                            ->required()
                            ->afterOrEqual('start_date')
                            ->reactive()
                            ->afterStateUpdated(fn (?string $state, callable $set, callable $get) => static::syncTotalDays($set, $get('start_date'), $get('end_date'))),
                        Forms\Components\TextInput::make('total_days')
                            ->numeric()
                            ->readOnly()
                            ->dehydrated()
                            ->suffix('days')
                            ->helperText('Calculated automatically'),
                        Forms\Components\Textarea::make('reason')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Status & Workflow')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->options([
                                LeaveRequest::STATUS_DRAFT => 'Draft',
                                LeaveRequest::STATUS_PENDING => 'Pending',
                                LeaveRequest::STATUS_APPROVED => 'Approved',
                                LeaveRequest::STATUS_REJECTED => 'Rejected',
                                LeaveRequest::STATUS_CANCELLED => 'Cancelled',
                            ])
                            ->disabled()
                            ->default(LeaveRequest::STATUS_DRAFT),
                        Forms\Components\Select::make('current_approver_id')
                            ->relationship('currentApprover', 'name')
                            ->label('Current Approver')
                            ->searchable()
                            ->preload()
                            ->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.user.name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('leaveType.name')
                    ->label('Type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_days')
                    ->label('Days')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => LeaveRequest::STATUS_PENDING,
                        'success' => LeaveRequest::STATUS_APPROVED,
                        'danger' => LeaveRequest::STATUS_REJECTED,
                        'gray' => LeaveRequest::STATUS_DRAFT,
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('currentApprover.name')
                    ->label('Approver')
                    ->placeholder('—'),
                Tables\Columns\BadgeColumn::make('certificate_status')
                    ->label('Certificate')
                    ->colors([
                        'warning' => LeaveRequest::CERT_STATUS_PENDING,
                        'info' => LeaveRequest::CERT_STATUS_SUBMITTED,
                        'success' => LeaveRequest::CERT_STATUS_VERIFIED,
                        'danger' => [LeaveRequest::CERT_STATUS_REJECTED, LeaveRequest::CERT_STATUS_EXPIRED, LeaveRequest::CERT_STATUS_OVERDUE],
                        'gray' => LeaveRequest::CERT_STATUS_RESOLVED,
                    ])
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('certificate_deadline_at')
                    ->label('Cert. Deadline')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        LeaveRequest::STATUS_DRAFT => 'Draft',
                        LeaveRequest::STATUS_PENDING => 'Pending',
                        LeaveRequest::STATUS_APPROVED => 'Approved',
                        LeaveRequest::STATUS_REJECTED => 'Rejected',
                        LeaveRequest::STATUS_CANCELLED => 'Cancelled',
                    ]),
                Tables\Filters\Filter::make('awaiting_me')
                    ->query(function (Builder $query): Builder {
                        $user = auth()->user();
                        $userRoles = $user?->roles->pluck('name')->toArray() ?? [];

                        return $query->where(function (Builder $q) use ($user, $userRoles) {
                            // Legacy single-approver path
                            $q->where('current_approver_id', $user?->id);

                            // Parallel workflow path: user has a pending step assigned to them
                            $q->orWhereHas('approvalSteps', function (Builder $stepQuery) use ($user, $userRoles) {
                                $stepQuery->where('status', 'pending')
                                    ->where(function ($sq) use ($user, $userRoles) {
                                        $sq->where('approver_id', $user?->id);
                                        if (! empty($userRoles)) {
                                            $sq->orWhere(function ($rq) use ($userRoles) {
                                                $rq->whereIn('approver_role', $userRoles)
                                                    ->whereNull('approver_id');
                                            });
                                        }
                                    });
                            });
                        });
                    })
                    ->label('Awaiting My Approval')
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (LeaveRequest $record): bool => Gate::allows('update', $record)),
                // Workflow-based approval action
                Tables\Actions\Action::make('workflow_approve')
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
                    ->visible(fn (LeaveRequest $record): bool => static::canApproveWithWorkflow($record))
                    ->action(fn (LeaveRequest $record, array $data) => static::handleWorkflowApprove($record, $data)),
                // Workflow-based rejection action
                Tables\Actions\Action::make('workflow_reject')
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
                    ->visible(fn (LeaveRequest $record): bool => static::canApproveWithWorkflow($record))
                    ->action(fn (LeaveRequest $record, array $data) => static::handleWorkflowReject($record, $data)),
                // Legacy approval action (for records without workflow)
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check')
                    ->requiresConfirmation()
                    ->color('success')
                    ->visible(fn (LeaveRequest $record): bool => static::canApproveLegacy($record))
                    ->action(fn (LeaveRequest $record) => Pages\LeaveRequestActionHandler::approve($record)),
                // Legacy rejection action (for records without workflow)
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (LeaveRequest $record): bool => static::canApproveLegacy($record))
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->action(fn (LeaveRequest $record, array $data) => Pages\LeaveRequestActionHandler::reject($record, $data['rejection_reason'])),
                Tables\Actions\Action::make('cancel')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (LeaveRequest $record): bool => Gate::allows('cancel', $record))
                    ->action(fn (LeaveRequest $record) => Pages\LeaveRequestActionHandler::cancel($record)),
                // Medical certificate review (provisional Sick Leave approval)
                Tables\Actions\Action::make('verify_certificate')
                    ->label(__('Verify Certificate'))
                    ->icon('heroicon-o-document-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (LeaveRequest $record): bool => static::canReviewCertificate($record))
                    ->action(fn (LeaveRequest $record) => static::handleVerifyCertificate($record)),
                Tables\Actions\Action::make('reject_certificate')
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
                    ->visible(fn (LeaveRequest $record): bool => static::canReviewCertificate($record))
                    ->action(fn (LeaveRequest $record, array $data) => static::handleRejectCertificate($record, $data['reason'])),
                // Overdue-certificate resolution: convert to Annual Leave / Alpha
                Tables\Actions\Action::make('resolve_certificate')
                    ->label(__('Resolve (Convert/Alpha)'))
                    ->icon('heroicon-o-scale')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(__('Resolve Overdue Sick Leave'))
                    ->modalDescription(fn (LeaveRequest $record): string => static::certificateResolutionPreview($record))
                    ->visible(fn (LeaveRequest $record): bool => static::canResolveCertificate($record))
                    ->action(fn (LeaveRequest $record) => static::handleResolveCertificate($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('start_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeaveRequests::route('/'),
            'create' => Pages\CreateLeaveRequest::route('/create'),
            'view' => Pages\ViewLeaveRequest::route('/{record}'),
            'edit' => Pages\EditLeaveRequest::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasAnyRole(['super_admin', 'hr-admin', 'hr-staff'])) {
            return $query;
        }

        // Build a query that includes:
        // 1. Leave requests where the user is the current approver
        // 2. Leave requests where the user has a pending ApprovalStep
        // 3. Leave requests from employees managed by this user (for managers)
        // 4. The user's own leave requests
        return $query->where(function (Builder $builder) use ($user) {
            // Include records where user is the assigned approver
            $builder->where('current_approver_id', $user->id);

            // Include records where user has a pending approval step
            $builder->orWhereHas('approvalSteps', function (Builder $stepQuery) use ($user) {
                $stepQuery->where('status', 'pending')
                    ->where(function ($q) use ($user) {
                        $q->where('approver_id', $user->id);
                        $userRoles = $user->roles->pluck('name')->toArray();
                        if (! empty($userRoles)) {
                            $q->orWhereIn('approver_role', $userRoles);
                        }
                    });
            });

            // Include records from employees managed by this user
            $employeeId = optional($user->employee)->id;
            if ($employeeId) {
                $builder->orWhereHas('employee.primaryPosition', fn (Builder $relation) => $relation->where('manager_id', $employeeId));
            }

            // Include the user's own leave requests
            $builder->orWhereHas('employee', fn (Builder $relation) => $relation->where('user_id', $user->id));
        });
    }

    protected static function syncTotalDays(callable $set, ?string $start, ?string $end): void
    {
        if (! $start || ! $end) {
            return;
        }

        $startDate = Carbon::parse($start);
        $endDate = Carbon::parse($end);

        $set('total_days', $startDate->diffInDays($endDate) + 1);
    }

    /**
     * Check if the current user can approve the record using workflow.
     */
    protected static function canApproveWithWorkflow(LeaveRequest $record): bool
    {
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
    protected static function canApproveLegacy(LeaveRequest $record): bool
    {
        // Only show legacy actions for pending records without approval workflow
        if (! $record->isPending() || $record->hasApprovalWorkflow()) {
            return false;
        }

        return Gate::allows('approve', $record);
    }

    /**
     * Handle workflow-based approval.
     */
    protected static function handleWorkflowApprove(LeaveRequest $record, array $data): void
    {
        $user = auth()->user();
        $step = $record->pendingStepFor($user);

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
    protected static function handleWorkflowReject(LeaveRequest $record, array $data): void
    {
        $user = auth()->user();
        $step = $record->pendingStepFor($user);

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
        } catch (\Exception $e) {
            Notification::make()
                ->title(__('Rejection Failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Only show certificate review actions once the employee has actually
     * submitted one, and only to users who can already edit this record.
     */
    protected static function canReviewCertificate(LeaveRequest $record): bool
    {
        return $record->certificate_status === LeaveRequest::CERT_STATUS_SUBMITTED
            && Gate::allows('update', $record);
    }

    protected static function handleVerifyCertificate(LeaveRequest $record): void
    {
        try {
            Pages\LeaveRequestActionHandler::verifyCertificate($record, auth()->user());

            Notification::make()
                ->title(__('Certificate Verified'))
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title(__('Verification Failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected static function handleRejectCertificate(LeaveRequest $record, string $reason): void
    {
        try {
            Pages\LeaveRequestActionHandler::rejectCertificate($record, auth()->user(), $reason);

            Notification::make()
                ->title(__('Certificate Rejected'))
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

    protected static function canResolveCertificate(LeaveRequest $record): bool
    {
        return $record->certificate_status === LeaveRequest::CERT_STATUS_OVERDUE
            && $record->status === LeaveRequest::STATUS_APPROVED
            && Gate::allows('update', $record);
    }

    /**
     * Human preview of what resolving will do, shown in the confirmation modal.
     * Mirrors SickLeaveResolutionService::resolve() arithmetic exactly — including
     * ensureBalance()'s lazy row provisioning — so the preview can never diverge
     * from the resolution. Warns when a locked payroll batch already covers the
     * leave period (resolution proceeds but won't retro-adjust paid payroll).
     */
    public static function certificateResolutionPreview(LeaveRequest $record): string
    {
        $record->loadMissing('employee');

        $annualType = LeaveType::where('code', LeaveBalanceService::CODE_ANNUAL)->first();

        $remaining = $annualType
            ? app(LeaveBalanceService::class)->ensureBalance($record->employee, $annualType, $record->start_date->year)->remaining_days
            : 0;

        $convertDays = min($remaining, $record->total_days);
        $alphaDays = $record->total_days - $convertDays;

        $preview = __(':remaining annual day(s) remaining. :converted day(s) will convert to Annual Leave (salary protected, no daily allowance); :alpha day(s) will become Alpha (prorated salary, no daily allowance).', [
            'remaining' => $remaining,
            'converted' => $convertDays,
            'alpha' => $alphaDays,
        ]);

        $lockedBatchExists = PayrunBatch::query()
            ->whereIn('status', [PayrunBatch::STATUS_PENDING_FINANCE, PayrunBatch::STATUS_PAID])
            ->where('period_start_date', '<=', $record->end_date)
            ->where('period_end_date', '>=', $record->start_date)
            ->exists();

        if ($lockedBatchExists) {
            $preview .= ' '.__('Warning: a payroll batch covering this period is already locked — this resolution will NOT retro-adjust paid payroll.');
        }

        return $preview;
    }

    protected static function handleResolveCertificate(LeaveRequest $record): void
    {
        try {
            $result = Pages\LeaveRequestActionHandler::resolveCertificate($record, auth()->user());

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
}
