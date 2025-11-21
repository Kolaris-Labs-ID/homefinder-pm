<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeaveRequestResource\Pages;
use App\Models\Hris\LeaveRequest;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
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
                    ->query(fn (Builder $query): Builder => $query->where('current_approver_id', auth()->id()))
                    ->label('Awaiting My Approval')
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (LeaveRequest $record): bool => Gate::allows('update', $record)),
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check')
                    ->requiresConfirmation()
                    ->color('success')
                    ->visible(fn (LeaveRequest $record): bool => Gate::allows('approve', $record))
                    ->action(fn (LeaveRequest $record) => Pages\LeaveRequestActionHandler::approve($record)),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (LeaveRequest $record): bool => Gate::allows('approve', $record))
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

        if ($user->hasAnyRole(['manager', 'head-division'])) {
            $employeeId = optional($user->employee)->id;

            if ($employeeId) {
                return $query->where(function (Builder $builder) use ($employeeId, $user) {
                    $builder->whereHas('employee.primaryPosition', fn (Builder $relation) => $relation->where('manager_id', $employeeId))
                        ->orWhereHas('employee', fn (Builder $relation) => $relation->where('user_id', $user->id));
                });
            }
        }

        return $query->whereHas('employee', fn (Builder $builder) => $builder->where('user_id', $user->id));
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
}

