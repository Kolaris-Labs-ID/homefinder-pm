<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentBundleResource\Pages;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleItem;
use App\Models\Hris\PaymentRequest;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\User;
use App\Services\Hris\PaymentBundlePaymentService;
use App\Services\Hris\PaymentBundleService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentBundleResource extends Resource
{
    protected static ?string $model = PaymentBundle::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationGroup = 'Finance Center';

    protected static ?string $navigationLabel = 'Payment Bundles';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return static::financeCan('payment_bundles.view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('bundle_number')
                    ->searchable()->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()->limit(40),
                Tables\Columns\TextColumn::make('status')
                    ->badge()->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('IDR')->sortable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created by'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('add_payment_request')
                    ->icon('heroicon-o-plus-circle')
                    ->label('Add PR')
                    ->visible(fn (PaymentBundle $r) => $r->status === PaymentBundle::STATUS_DRAFT && static::financeCan('payment_bundles.update_draft'))
                    ->form([
                        Forms\Components\Select::make('payment_request_id')
                            ->label('Payment Request')
                            ->options(function () {
                                return PaymentRequest::query()
                                    ->whereIn('status', [
                                        PaymentRequest::STATUS_APPROVED_BY_COO,
                                        PaymentRequest::STATUS_APPROVED_FOR_BUNDLE,
                                    ])
                                    ->whereNotIn('id', function ($q) {
                                        $q->select('source_id')
                                            ->from('payment_bundle_items')
                                            ->where('source_type', PaymentBundleItem::SOURCE_PAYMENT_REQUEST)
                                            ->where('status', PaymentBundleItem::STATUS_ACTIVE);
                                    })
                                    ->get()
                                    ->mapWithKeys(fn ($pr) => [
                                        $pr->id => "{$pr->payment_request_number} — {$pr->payee_name} — ".number_format((float) $pr->amount, 2),
                                    ]);
                            })
                            ->required()
                            ->searchable(),
                    ])
                    ->action(fn (PaymentBundle $r, array $data) => static::run(
                        fn () => app(PaymentBundleService::class)->addPaymentRequest(
                            $r,
                            PaymentRequest::findOrFail($data['payment_request_id']),
                            auth()->user()
                        ),
                        'Payment request added to bundle'
                    )),

                Tables\Actions\Action::make('add_weekly_allowance')
                    ->icon('heroicon-o-plus-circle')
                    ->label('Add Weekly Allowance')
                    ->visible(fn (PaymentBundle $r) => $r->status === PaymentBundle::STATUS_DRAFT && static::financeCan('payment_bundles.update_draft'))
                    ->form([
                        Forms\Components\Select::make('weekly_allowance_batch_id')
                            ->label('Weekly Allowance Batch')
                            ->options(function () {
                                return WeeklyAllowanceBatch::query()
                                    ->where('status', WeeklyAllowanceBatch::STATUS_ELIGIBLE_FOR_BUNDLE)
                                    ->whereNotIn('id', function ($q) {
                                        $q->select('source_id')
                                            ->from('payment_bundle_items')
                                            ->where('source_type', PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH)
                                            ->where('status', PaymentBundleItem::STATUS_ACTIVE);
                                    })
                                    ->get()
                                    ->mapWithKeys(fn ($wab) => [
                                        $wab->id => "WAB-{$wab->id} — {$wab->week_start_date} to {$wab->week_end_date} — ".number_format((float) $wab->total_amount, 2),
                                    ]);
                            })
                            ->required()
                            ->searchable(),
                    ])
                    ->action(fn (PaymentBundle $r, array $data) => static::run(
                        fn () => app(PaymentBundleService::class)->addWeeklyAllowanceBatch(
                            $r,
                            WeeklyAllowanceBatch::findOrFail($data['weekly_allowance_batch_id']),
                            auth()->user()
                        ),
                        'Weekly allowance batch added to bundle'
                    )),

                Tables\Actions\Action::make('submit_for_signature')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->visible(fn (PaymentBundle $r) => $r->status === PaymentBundle::STATUS_DRAFT && static::financeCan('payment_bundles.submit_for_signature'))
                    ->form([
                        Forms\Components\Select::make('finance_signer_1')
                            ->label('Finance Signer 1')
                            ->options(fn () => User::permission('payment_bundles.sign.finance')->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('finance_signer_2')
                            ->label('Finance Signer 2')
                            ->options(fn () => User::permission('payment_bundles.sign.finance')->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('executive_signer')
                            ->label('Executive Signer')
                            ->options(fn () => User::permission('payment_bundles.sign.executive')->pluck('name', 'id'))
                            ->required()
                            ->searchable(),
                    ])
                    ->action(fn (PaymentBundle $r, array $data) => static::run(
                        fn () => app(PaymentBundleService::class)->submitForSignature(
                            $r,
                            [
                                'finance_signer_1' => $data['finance_signer_1'],
                                'finance_signer_2' => $data['finance_signer_2'],
                                'executive_signer' => $data['executive_signer'],
                            ],
                            auth()->user()
                        ),
                        'Bundle submitted for signature'
                    )),

                Tables\Actions\Action::make('begin_payment_recording')
                    ->icon('heroicon-o-play-circle')
                    ->color('info')
                    ->visible(fn (PaymentBundle $r) => $r->status === PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT && static::financeCan('payment_bundles.mark_paid'))
                    ->action(fn (PaymentBundle $r) => static::run(
                        fn () => app(PaymentBundlePaymentService::class)->beginPaymentRecording($r, auth()->user()),
                        'Payment recording started'
                    )),

                Tables\Actions\Action::make('mark_paid')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (PaymentBundle $r) => in_array($r->status, [
                        PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT,
                        PaymentBundle::STATUS_PAYMENT_RECORDING,
                        PaymentBundle::STATUS_PAYMENT_PROOF_REJECTED,
                    ], true) && static::financeCan('payment_bundles.mark_paid'))
                    ->form([
                        Forms\Components\FileUpload::make('proof')
                            ->label('Payment Proof')
                            ->required()
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                            ->storeFiles(false),
                        Forms\Components\TextInput::make('payment_reference')
                            ->label('Payment Reference'),
                        Forms\Components\Textarea::make('payment_notes')
                            ->label('Notes'),
                    ])
                    ->action(fn (PaymentBundle $r, array $data) => static::run(
                        fn () => app(PaymentBundlePaymentService::class)->markPaid(
                            $r,
                            auth()->user(),
                            $data['proof'],
                            $data['payment_reference'] ?? null,
                            $data['payment_notes'] ?? null,
                        ),
                        'Bundle marked as paid'
                    )),

                Tables\Actions\Action::make('reject_payment_proof')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (PaymentBundle $r) => $r->status === PaymentBundle::STATUS_PAID && static::financeCan('payment_bundles.reject_payment_proof'))
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Rejection Reason')
                            ->required(),
                    ])
                    ->action(fn (PaymentBundle $r, array $data) => static::run(
                        fn () => app(PaymentBundlePaymentService::class)->rejectPaymentProof($r, auth()->user(), $data['reason']),
                        'Payment proof rejected'
                    )),

                Tables\Actions\Action::make('cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->visible(fn (PaymentBundle $r) => $r->status === PaymentBundle::STATUS_DRAFT && static::financeCan('payment_bundles.cancel'))
                    ->requiresConfirmation()
                    ->action(fn (PaymentBundle $r) => static::run(
                        fn () => app(PaymentBundleService::class)->cancel($r, auth()->user()),
                        'Bundle cancelled'
                    )),

                Tables\Actions\Action::make('void')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (PaymentBundle $r) => ! in_array($r->status, [
                        PaymentBundle::STATUS_PAID,
                        PaymentBundle::STATUS_VOIDED,
                        PaymentBundle::STATUS_CANCELLED,
                        PaymentBundle::STATUS_REJECTED,
                    ], true) && static::financeCan('payment_bundles.void'))
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Void Reason')
                            ->required(),
                    ])
                    ->action(fn (PaymentBundle $r, array $data) => static::run(
                        fn () => app(PaymentBundleService::class)->void($r, auth()->user(), $data['reason']),
                        'Bundle voided'
                    )),

                Tables\Actions\Action::make('download_unsigned_pdf')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->label('Unsigned PDF')
                    ->visible(fn (PaymentBundle $r) => filled($r->unsigned_pdf_attachment_id) && static::financeCan('payment_bundles.download_pdf'))
                    ->url(fn (PaymentBundle $r) => route('finance.payment-bundles.unsigned-pdf', $r))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('download_signed_pdf')
                    ->icon('heroicon-o-document-arrow-down')
                    ->label('Signed PDF')
                    ->visible(fn (PaymentBundle $r) => filled($r->signed_pdf_attachment_id) && static::financeCan('payment_bundles.download_pdf'))
                    ->url(fn (PaymentBundle $r) => route('finance.payment-bundles.signed-pdf', $r))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('bundle_number')->disabled(),
            Forms\Components\TextInput::make('title')->disabled(),
            Forms\Components\TextInput::make('status')->disabled(),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Bundle')->schema([
                TextEntry::make('bundle_number'),
                TextEntry::make('title')->placeholder('—'),
                TextEntry::make('status')->badge(),
                TextEntry::make('total_amount')->money('IDR'),
                TextEntry::make('currency'),
                TextEntry::make('period_start')->date()->placeholder('—'),
                TextEntry::make('period_end')->date()->placeholder('—'),
                TextEntry::make('creator.name')->label('Created by'),
                TextEntry::make('created_at')->dateTime(),
                TextEntry::make('authorized_at')->dateTime()->placeholder('—'),
                TextEntry::make('paid_at')->dateTime()->placeholder('—'),
                TextEntry::make('payment_reference')->placeholder('—'),
            ])->columns(2),

            Section::make('Canonical Hash')->schema([
                TextEntry::make('canonical_payload_hash')
                    ->label('Canonical payload hash')
                    ->limit(20)
                    ->placeholder('—'),
            ])->columns(1),

            Section::make('Items')->schema([
                RepeatableEntry::make('activeItems')
                    ->label('')
                    ->schema([
                        TextEntry::make('payee_name'),
                        TextEntry::make('payee_bank_account_last4')
                            ->label('Account')
                            ->formatStateUsing(fn ($state) => $state ? '•••• '.$state : '—'),
                        TextEntry::make('amount')->money('IDR'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('source_type')->badge(),
                    ])
                    ->columns(5),
            ]),

            Section::make('Signatures')->schema([
                RepeatableEntry::make('signatures')
                    ->label('')
                    ->schema([
                        TextEntry::make('signature_slot')
                            ->label('Slot')
                            ->formatStateUsing(fn ($state) => $state instanceof \App\Enums\PaymentBundleSignatureSlot ? $state->label() : $state),
                        TextEntry::make('assignedUser.name')
                            ->label('Assigned to')
                            ->placeholder('—'),
                        TextEntry::make('signer.name')
                            ->label('Signed by')
                            ->placeholder('—'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('signed_at')->dateTime()->placeholder('—'),
                    ])
                    ->columns(5),
            ]),

            Section::make('Documents')->schema([
                TextEntry::make('unsigned_pdf_attachment_id')
                    ->label('Unsigned PDF')
                    ->formatStateUsing(fn ($state, $record) => filled($state) ? 'Available' : 'Not generated')
                    ->visible(fn ($record) => static::financeCan('payment_bundles.download_pdf'))
                    ->url(fn ($record) => filled($record->unsigned_pdf_attachment_id) ? route('finance.payment-bundles.unsigned-pdf', $record) : null),
                TextEntry::make('signed_pdf_attachment_id')
                    ->label('Signed PDF')
                    ->formatStateUsing(fn ($state, $record) => filled($state) ? 'Available' : 'Not generated')
                    ->visible(fn ($record) => static::financeCan('payment_bundles.download_pdf'))
                    ->url(fn ($record) => filled($record->signed_pdf_attachment_id) ? route('finance.payment-bundles.signed-pdf', $record) : null),
            ])->columns(2)->visible(fn ($record) => static::financeCan('payment_bundles.download_pdf')),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentBundles::route('/'),
            'view' => Pages\ViewPaymentBundle::route('/{record}'),
        ];
    }

    public static function financeCan(string $permission): bool
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

    public static function runStatic(callable $fn, string $successMessage): void
    {
        static::run($fn, $successMessage);
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
