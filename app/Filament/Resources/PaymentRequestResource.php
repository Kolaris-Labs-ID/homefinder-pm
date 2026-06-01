<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentRequestResource\Pages;
use App\Models\Hris\FinanceAttachment;
use App\Models\Hris\PaymentRequest;
use App\Services\Hris\FinanceAttachmentService;
use App\Services\Hris\PaymentRequestService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Finance workbench for Payment Requests (EXPENSE_MODULE.MD Phase 11/12).
 * Lifecycle actions (submit to COO, mark paid w/ proof, reconcile, void, reverse) all run
 * through PaymentRequestService — bank account is shown masked (last4) only.
 */
class PaymentRequestResource extends Resource
{
    protected static ?string $model = PaymentRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Finance Center';

    protected static ?string $navigationLabel = 'Payment Requests';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        return static::financeCan('payment_requests.view_all');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('payment_request_number')->disabled(),
            Forms\Components\TextInput::make('payee_name')->disabled(),
            Forms\Components\TextInput::make('bank_name')->disabled(),
            Forms\Components\TextInput::make('bank_account_last4')->label('Bank acct (last4)')->disabled(),
            Forms\Components\TextInput::make('amount')->prefix(fn ($record) => $record?->currency)->disabled(),
            Forms\Components\TextInput::make('source_type')->disabled(),
            Forms\Components\TextInput::make('status')->disabled(),
            Forms\Components\TextInput::make('approval_snapshot_hash')->label('Approval snapshot hash')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('payment_request_number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('payee_name')->searchable(),
                Tables\Columns\TextColumn::make('bank_account_last4')->label('Acct')->formatStateUsing(fn ($state) => $state ? '•••• '.$state : '—'),
                Tables\Columns\TextColumn::make('amount')->money('IDR')->sortable(),
                Tables\Columns\TextColumn::make('source_type')->badge(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\IconColumn::make('approval_snapshot_hash')->label('Sealed')->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('submit_to_coo')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (PaymentRequest $r) => in_array($r->status, [PaymentRequest::STATUS_DRAFT, PaymentRequest::STATUS_NEEDS_CLARIFICATION], true) && static::financeCan('payment_requests.submit_to_coo'))
                    ->requiresConfirmation()
                    ->action(fn (PaymentRequest $r) => static::run(fn () => app(PaymentRequestService::class)->submitToCoo($r), 'Submitted to COO')),

                Tables\Actions\Action::make('mark_paid')
                    ->icon('heroicon-o-check-circle')->color('success')
                    ->visible(fn (PaymentRequest $r) => $r->status === PaymentRequest::STATUS_APPROVED_BY_COO && static::financeCan('payment_requests.mark_paid'))
                    ->form([
                        Forms\Components\TextInput::make('payment_reference')->required(),
                        Forms\Components\FileUpload::make('proof')->label('Payment proof')->required()
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])->storeFiles(false),
                    ])
                    ->action(fn (PaymentRequest $r, array $data) => static::run(function () use ($r, $data) {
                        $proof = app(FinanceAttachmentService::class)->store($r, $data['proof'], FinanceAttachment::ROLE_PAYMENT_PROOF, auth()->user());
                        app(PaymentRequestService::class)->markPaid($r, auth()->user(), $data['payment_reference'], $proof);
                    }, 'Marked paid — pending reconciliation')),

                Tables\Actions\Action::make('reconcile')
                    ->icon('heroicon-o-scale')
                    ->visible(fn (PaymentRequest $r) => $r->status === PaymentRequest::STATUS_PAID_PENDING_RECONCILIATION && static::financeCan('payment_requests.reconcile'))
                    ->form([
                        Forms\Components\TextInput::make('amount')->numeric()->required(),
                        Forms\Components\TextInput::make('payee_name')->required(),
                        Forms\Components\TextInput::make('bank_account_last4')->required()->maxLength(4),
                        Forms\Components\TextInput::make('reference')->required(),
                    ])
                    ->action(fn (PaymentRequest $r, array $data) => static::run(fn () => app(PaymentRequestService::class)->reconcile($r, auth()->user(), $data), 'Reconciliation recorded')),

                Tables\Actions\Action::make('void')
                    ->icon('heroicon-o-no-symbol')->color('danger')
                    ->visible(fn (PaymentRequest $r) => static::financeCan('payment_requests.void'))
                    ->form([Forms\Components\Textarea::make('reason')->required()])
                    ->action(fn (PaymentRequest $r, array $data) => static::run(fn () => app(PaymentRequestService::class)->void($r, auth()->user(), $data['reason']), 'Payment request voided')),

                Tables\Actions\Action::make('reverse')
                    ->icon('heroicon-o-arrow-uturn-left')->color('danger')
                    ->visible(fn (PaymentRequest $r) => static::financeCan('payment_requests.reverse'))
                    ->form([Forms\Components\Textarea::make('reason')->required()])
                    ->action(fn (PaymentRequest $r, array $data) => static::run(fn () => app(PaymentRequestService::class)->reverse($r, auth()->user(), $data['reason']), 'Payment reversed')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentRequests::route('/'),
            'view' => Pages\ViewPaymentRequest::route('/{record}'),
        ];
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
