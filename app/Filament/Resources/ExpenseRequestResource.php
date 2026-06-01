<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseRequestResource\Pages;
use App\Models\Hris\ExpenseRequest;
use App\Services\Hris\ExpenseRequestService;
use App\Services\Hris\PaymentRequestService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Finance review workbench for Expense Requests (EXPENSE_MODULE.MD Phase 11/12).
 * Read-mostly: employees create ERs from mobile; finance verifies/rejects/requests revision
 * and converts a verified ER into a PaymentRequest. All actions go through the services.
 */
class ExpenseRequestResource extends Resource
{
    protected static ?string $model = ExpenseRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = 'Finance Center';

    protected static ?string $navigationLabel = 'Expense Requests';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return static::financeCan('expense_requests.view_all');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('request_number')->disabled(),
            Forms\Components\TextInput::make('title')->disabled(),
            Forms\Components\TextInput::make('amount')->prefix(fn ($record) => $record?->currency)->disabled(),
            Forms\Components\TextInput::make('payment_mode')->disabled(),
            Forms\Components\TextInput::make('status')->disabled(),
            Forms\Components\TextInput::make('payee_bank_account_last4')->label('Bank acct (last4)')->disabled(),
            Forms\Components\Textarea::make('finance_notes')->disabled()->columnSpanFull(),
            Forms\Components\Textarea::make('rejection_reason')->disabled()->columnSpanFull()
                ->visible(fn ($record) => filled($record?->rejection_reason)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('request_number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('employee.user.name')->label('Employee')->searchable(),
                Tables\Columns\TextColumn::make('title')->limit(30),
                Tables\Columns\TextColumn::make('amount')->money('IDR')->sortable(),
                Tables\Columns\TextColumn::make('payment_mode')->badge(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('expense_date')->date()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(fn () => collect([
                    ExpenseRequest::STATUS_SUBMITTED, ExpenseRequest::STATUS_UNDER_FINANCE_REVIEW,
                    ExpenseRequest::STATUS_NEEDS_REVISION, ExpenseRequest::STATUS_FINANCE_VERIFIED,
                    ExpenseRequest::STATUS_PR_CREATED, ExpenseRequest::STATUS_REJECTED,
                ])->mapWithKeys(fn ($s) => [$s => str($s)->headline()])->all()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('verify')
                    ->icon('heroicon-o-check-badge')->color('success')
                    ->visible(fn (ExpenseRequest $r) => static::canReview($r) && static::financeCan('expense_requests.verify'))
                    ->form([Forms\Components\Textarea::make('notes')])
                    ->action(fn (ExpenseRequest $r, array $data) => static::run(fn () => app(ExpenseRequestService::class)->verify($r, auth()->user(), $data['notes'] ?? null), 'Expense request verified')),

                Tables\Actions\Action::make('request_revision')
                    ->icon('heroicon-o-arrow-uturn-left')->color('warning')
                    ->visible(fn (ExpenseRequest $r) => static::canReview($r) && static::financeCan('expense_requests.request_revision'))
                    ->form([Forms\Components\Textarea::make('note')->required()])
                    ->action(fn (ExpenseRequest $r, array $data) => static::run(fn () => app(ExpenseRequestService::class)->requestRevision($r, auth()->user(), $data['note']), 'Revision requested')),

                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')->color('danger')
                    ->visible(fn (ExpenseRequest $r) => static::canReview($r) && static::financeCan('expense_requests.reject'))
                    ->form([Forms\Components\Textarea::make('reason')->required()])
                    ->action(fn (ExpenseRequest $r, array $data) => static::run(fn () => app(ExpenseRequestService::class)->reject($r, auth()->user(), $data['reason']), 'Expense request rejected')),

                Tables\Actions\Action::make('create_payment_request')
                    ->icon('heroicon-o-banknotes')->color('primary')
                    ->visible(fn (ExpenseRequest $r) => $r->status === ExpenseRequest::STATUS_FINANCE_VERIFIED && ! $r->isRecordOnly() && static::financeCan('payment_requests.create'))
                    ->requiresConfirmation()
                    ->action(fn (ExpenseRequest $r) => static::run(fn () => app(PaymentRequestService::class)->createFromExpenseRequest($r, auth()->user()), 'Payment request created from ER')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenseRequests::route('/'),
            'view' => Pages\ViewExpenseRequest::route('/{record}'),
        ];
    }

    protected static function canReview(ExpenseRequest $record): bool
    {
        return in_array($record->status, [
            ExpenseRequest::STATUS_SUBMITTED,
            ExpenseRequest::STATUS_UNDER_FINANCE_REVIEW,
        ], true);
    }

    protected static function financeCan(string $permission): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        if ($user->hasRole('super_admin')) {
            return true;
        }
        try {
            return $user->hasPermissionTo($permission, 'web');
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected static function run(callable $fn, string $successMessage): void
    {
        try {
            $fn();
            Notification::make()->title($successMessage)->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Action failed')->body($e->getMessage())->danger()->send();
        }
    }
}
