<?php

namespace App\Filament\Resources;

use App\Filament\Actions\ApproveAction;
use App\Filament\Actions\RejectAction;
use App\Filament\Resources\LeaveRequestResource\Pages;
use App\Models\Hris\Employee;
use App\Models\Hris\LeaveRequest;
use App\Models\User;
use App\Services\Hris\ApprovalWorkflowService;
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
                            ->afterStateHydrated(function (?LeaveRequest $record, $state): void {
                                $employee = filled($state) ? Employee::query()->with('user')->find($state) : null;
                                $user = filled($state) ? User::query()->find($state) : null;

                                // #region agent log
                                @file_put_contents(
                                    '/Users/arirudiana/Devs/hris-phi/.cursor/debug-21491e.log',
                                    json_encode([
                                        'sessionId' => '21491e',
                                        'runId' => 'initial',
                                        'hypothesisId' => 'H5',
                                        'location' => 'LeaveRequestResource.php',
                                        'message' => 'employee select hydrated',
                                        'data' => [
                                            'record_id' => data_get($record, 'id'),
                                            'state' => $state,
                                            'record_employee_id' => data_get($record, 'employee_id'),
                                            'employee_name_for_state' => $employee?->user?->name,
                                            'employee_user_id_for_state' => $employee?->user_id,
                                            'user_name_for_same_state' => $user?->name,
                                            'state_matches_record_employee_id' => $state === data_get($record, 'employee_id'),
                                        ],
                                        'timestamp' => round(microtime(true) * 1000),
                                    ], JSON_UNESCAPED_SLASHES) . PHP_EOL,
                                    FILE_APPEND
                                );
                                // #endregion
                            })
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
                                        if (!empty($userRoles)) {
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
                        if (!empty($userRoles)) {
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
    protected static function canApproveLegacy(LeaveRequest $record): bool
    {
        // Only show legacy actions for pending records without approval workflow
        if (!$record->isPending() || $record->hasApprovalWorkflow()) {
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

        if (!$step) {
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

        if (!$step) {
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
}

