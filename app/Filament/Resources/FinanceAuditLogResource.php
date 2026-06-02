<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FinanceAuditLogResource\Pages;
use App\Models\Hris\FinanceAuditLog;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FinanceAuditLogResource extends Resource
{
    protected static ?string $model = FinanceAuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Finance Center';

    protected static ?string $navigationLabel = 'Audit Log';

    protected static ?int $navigationSort = 9;

    public static function canViewAny(): bool
    {
        return static::financeCan('finance_center.view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Event')->schema([
                TextEntry::make('created_at')->dateTime()->label('Time'),
                TextEntry::make('event_type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => static::eventLabel($state))
                    ->color(fn (?string $state) => static::eventColor($state)),
                TextEntry::make('actor.name')->label('Actor')->placeholder('—'),
                TextEntry::make('actor_role')->badge()->placeholder('—'),
                TextEntry::make('reason')->placeholder('—')->columnSpanFull(),
                TextEntry::make('ip_address')->placeholder('—'),
                TextEntry::make('user_agent')->placeholder('—')->columnSpanFull(),
            ])->columns(2),

            Section::make('Target')->schema([
                TextEntry::make('auditable_type')->label('Type'),
                TextEntry::make('auditable_id')->label('ID'),
            ])->columns(2),

            Section::make('Integrity')->schema([
                TextEntry::make('previous_hash')->label('Previous hash')->limit(20)->copyable()->placeholder('—'),
                TextEntry::make('current_hash')->label('Current hash')->limit(20)->copyable(),
            ])->columns(2),

            Section::make('Snapshots')->schema([
                KeyValueEntry::make('before_snapshot')
                    ->label('Before')
                    ->visible(fn ($record) => filled($record->before_snapshot)),
                KeyValueEntry::make('after_snapshot')
                    ->label('After')
                    ->visible(fn ($record) => filled($record->after_snapshot)),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Time'),
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Event type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => static::eventLabel($state))
                    ->color(fn (?string $state) => static::eventColor($state))
                    ->searchable(),
                Tables\Columns\TextColumn::make('auditable_id')
                    ->label('Target')
                    ->state(fn ($record) => class_basename($record->auditable_type).' #'.$record->auditable_id),
                Tables\Columns\TextColumn::make('actor.name')
                    ->label('Actor')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('actor_role')
                    ->badge()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('reason')
                    ->limit(40)
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('event_type')
                    ->options(fn () => FinanceAuditLog::query()
                        ->distinct()
                        ->orderBy('event_type')
                        ->pluck('event_type')
                        ->mapWithKeys(fn (string $type) => [$type => static::eventLabel($type)])
                        ->all()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFinanceAuditLogs::route('/'),
            'view' => Pages\ViewFinanceAuditLog::route('/{record}'),
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

    public static function eventLabel(?string $type): string
    {
        if (blank($type)) {
            return '—';
        }

        $verb = static::eventMeta()[$type][0]
            ?? (string) str(str_contains($type, '.') ? str($type)->after('.') : $type)->headline();

        $prefix = static::eventPrefix($type);

        return $prefix ? "{$prefix} · {$verb}" : $verb;
    }

    public static function eventColor(?string $type): string
    {
        if (blank($type)) {
            return 'gray';
        }

        return static::eventMeta()[$type][1] ?? 'gray';
    }

    protected static function eventPrefix(string $type): ?string
    {
        return match (true) {
            str_starts_with($type, 'expense_request.') => 'Expense',
            str_starts_with($type, 'finance_attachment.') => 'Attachment',
            str_starts_with($type, 'payment_request.') => 'Payment Req',
            str_starts_with($type, 'payment_bundle.') => 'Bundle',
            str_starts_with($type, 'batch_') => 'Batch',
            default => null,
        };
    }

    /** @return array<string, array{0: string, 1: string}> event_type => [label, color] */
    protected static function eventMeta(): array
    {
        return [
            'expense_request.draft_created' => ['Draft Created', 'info'],
            'expense_request.submitted' => ['Submitted', 'info'],
            'expense_request.finance_verified' => ['Finance Verified', 'success'],
            'expense_request.revision_requested' => ['Revision Requested', 'warning'],
            'expense_request.revised' => ['Revised', 'info'],
            'expense_request.rejected' => ['Rejected', 'danger'],
            'expense_request.cancelled' => ['Cancelled', 'gray'],
            'expense_request.converted_to_pr' => ['Converted to Payment Request', 'success'],
            'expense_request.record_only_closed' => ['Closed (Record Only)', 'gray'],

            'finance_attachment.uploaded' => ['Uploaded', 'info'],
            'finance_attachment.locked' => ['Locked', 'gray'],
            'finance_attachment.deleted' => ['Deleted', 'danger'],

            'payment_request.created_from_expense_request' => ['Created from Expense', 'info'],
            'payment_request.approved_by_coo' => ['Approved by COO', 'success'],
            'payment_request.rejected_by_coo' => ['Rejected by COO', 'danger'],
            'payment_request.marked_paid' => ['Marked Paid', 'success'],
            'payment_request.reversed' => ['Reversed', 'danger'],
            'payment_request.voided' => ['Voided', 'danger'],

            'payment_bundle.created' => ['Created', 'info'],
            'payment_bundle.item_added' => ['Item Added', 'info'],
            'payment_bundle.item_removed' => ['Item Removed', 'gray'],
            'payment_bundle.item_released' => ['Item Released', 'gray'],
            'payment_bundle.submitted_for_signature' => ['Submitted for Signature', 'info'],
            'payment_bundle.signature_link_created' => ['Signature Link Created', 'gray'],
            'payment_bundle.signature_submitted' => ['Signature Submitted', 'success'],
            'payment_bundle.signature_rejected' => ['Signature Rejected', 'danger'],
            'payment_bundle.signature_revoked' => ['Signature Revoked', 'danger'],
            'payment_bundle.signature_voided' => ['Signature Voided', 'danger'],
            'payment_bundle.signed_pdf_generated' => ['Signed PDF Generated', 'success'],
            'payment_bundle.unsigned_pdf_generated' => ['Draft PDF Generated', 'gray'],
            'payment_bundle.payload_hash_generated' => ['Payload Hash Generated', 'gray'],
            'payment_bundle.authorized' => ['Authorized', 'success'],
            'payment_bundle.payment_recording_started' => ['Payment Recording Started', 'info'],
            'payment_bundle.payment_proof_uploaded' => ['Payment Proof Uploaded', 'info'],
            'payment_bundle.payment_proof_rejected' => ['Payment Proof Rejected', 'danger'],
            'payment_bundle.marked_paid' => ['Marked Paid', 'success'],
            'payment_bundle.rejected' => ['Rejected', 'danger'],
            'payment_bundle.cancelled' => ['Cancelled', 'gray'],
            'payment_bundle.voided' => ['Voided', 'danger'],

            'batch_bank_detail_revealed' => ['Bank Detail Revealed', 'warning'],
            'batch_source_added_to_bundle' => ['Added to Bundle', 'info'],
            'batch_source_blocked_missing_data' => ['Blocked: Missing Data', 'danger'],
            'batch_source_drift_detected' => ['Drift Detected', 'danger'],
            'batch_source_marked_paid' => ['Marked Paid', 'success'],
            'batch_source_released_from_bundle' => ['Released from Bundle', 'gray'],
        ];
    }
}
