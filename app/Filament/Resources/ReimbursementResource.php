<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReimbursementResource\Pages;
use App\Models\Hris\Employee;
use App\Models\Hris\Reimbursement;
use App\Services\Hris\ApprovalWorkflowService;
use App\Services\Hris\ReimbursementService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReimbursementResource extends Resource
{
    protected static ?string $model = Reimbursement::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Payroll';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Reimbursement Details')
                    ->schema([
                        Forms\Components\Select::make('employee_id')
                            ->label('Employee')
                            ->options(Employee::active()->with('user')->get()->pluck('user.name', 'id'))
                            ->searchable()
                            ->required(),
                        Forms\Components\Select::make('type')
                            ->options(Reimbursement::getTypeLabels())
                            ->required(),
                        Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->required()
                            ->prefix('Rp')
                            ->minValue(1),
                        Forms\Components\Textarea::make('description')
                            ->rows(3),
                        Forms\Components\FileUpload::make('receipt_file_path')
                            ->label('Receipt')
                            ->disk('private')
                            ->directory('reimbursements')
                            ->acceptedFileTypes(['image/*', 'application/pdf'])
                            ->maxSize(5120),
                    ])->columns(2),
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
                Tables\Columns\TextColumn::make('type')
                    ->badge(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('IDR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'rejected' => 'danger',
                        'paid' => 'success',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(Reimbursement::getStatusLabels()),
                Tables\Filters\SelectFilter::make('type')
                    ->options(Reimbursement::getTypeLabels()),
            ])
            ->actions([
                // Workflow-based approval action
                Tables\Actions\Action::make('workflow_approve')
                    ->label(__('Approve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->modalHeading(__('Approve Reimbursement'))
                    ->modalDescription(__('Are you sure you want to approve this reimbursement?'))
                    ->modalSubmitActionLabel(__('Approve'))
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label(__('Notes (Optional)'))
                            ->placeholder(__('Add any notes or comments...'))
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->visible(fn (Reimbursement $record): bool => static::canApproveWithWorkflow($record))
                    ->action(fn (Reimbursement $record, array $data) => static::handleWorkflowApprove($record, $data)),
                // Workflow-based rejection action
                Tables\Actions\Action::make('workflow_reject')
                    ->label(__('Reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->modalHeading(__('Reject Reimbursement'))
                    ->modalDescription(__('Please provide a reason for rejecting this reimbursement.'))
                    ->modalSubmitActionLabel(__('Reject'))
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label(__('Rejection Reason'))
                            ->placeholder(__('Please explain why this reimbursement is being rejected...'))
                            ->required()
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->visible(fn (Reimbursement $record): bool => static::canApproveWithWorkflow($record))
                    ->action(fn (Reimbursement $record, array $data) => static::handleWorkflowReject($record, $data)),
                // Legacy approval action (for records without workflow)
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Reimbursement $record) => static::canApproveLegacy($record))
                    ->requiresConfirmation()
                    ->action(function (Reimbursement $record) {
                        app(ReimbursementService::class)->approve($record);
                        Notification::make()->title('Reimbursement Approved')->success()->send();
                    }),
                // Legacy rejection action (for records without workflow)
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Reimbursement $record) => static::canApproveLegacy($record))
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->required()
                            ->label('Reason for Rejection'),
                    ])
                    ->action(function (Reimbursement $record, array $data) {
                        app(ReimbursementService::class)->reject($record, $data['rejection_reason']);
                        Notification::make()->title('Reimbursement Rejected')->success()->send();
                    }),
                Tables\Actions\Action::make('markAsPaid')
                    ->label('Mark as Paid')
                    ->icon('heroicon-o-banknotes')
                    ->color('info')
                    ->visible(fn (Reimbursement $record) => $record->canMarkAsPaid())
                    ->requiresConfirmation()
                    ->action(function (Reimbursement $record) {
                        app(ReimbursementService::class)->markAsPaid($record, auth()->user());
                        Notification::make()->title('Reimbursement Marked as Paid')->success()->send();
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Reimbursement $record) => $record->status === 'pending'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReimbursements::route('/'),
            'create' => Pages\CreateReimbursement::route('/create'),
            'view' => Pages\ViewReimbursement::route('/{record}'),
            'edit' => Pages\EditReimbursement::route('/{record}/edit'),
        ];
    }

    /**
     * Check if the current user can approve the record using workflow.
     */
    protected static function canApproveWithWorkflow(Reimbursement $record): bool
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
    protected static function canApproveLegacy(Reimbursement $record): bool
    {
        // Only show legacy actions for pending records without approval workflow
        if (!$record->canApprove() || $record->hasApprovalWorkflow()) {
            return false;
        }

        return true;
    }

    /**
     * Handle workflow-based approval.
     */
    protected static function handleWorkflowApprove(Reimbursement $record, array $data): void
    {
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
                ->title(__('Reimbursement Approved'))
                ->body(__('The reimbursement has been approved successfully.'))
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
    protected static function handleWorkflowReject(Reimbursement $record, array $data): void
    {
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
                ->title(__('Reimbursement Rejected'))
                ->body(__('The reimbursement has been rejected.'))
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
