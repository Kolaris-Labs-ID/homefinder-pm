<?php

namespace App\Filament\Pages;

use App\Models\Hris\PaymentRequest;
use App\Services\Hris\PaymentRequestService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Payment approval queue (EXPENSE_MODULE.MD Phase 12).
 * Read-only business view of PRs awaiting approval: approve / reject / request clarification.
 * Approver never edits money/payee/bank, marks paid, or reconciles. Approval mints the immutable snapshot.
 */
class PaymentApprovalView extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $navigationLabel = 'Payment Approvals';

    protected static ?string $title = 'Payment Approvals';

    protected static ?string $navigationGroup = 'Finance Center';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'payment-approvals';

    protected static string $view = 'filament.pages.payment-approval-view';

    public static function canAccess(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }
        if ($user->hasRole('super_admin')) {
            return true;
        }
        try {
            return $user->hasPermissionTo('payment_requests.approve', 'web');
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(PaymentRequest::query()->where('status', PaymentRequest::STATUS_SUBMITTED_TO_COO))
            ->columns([
                Tables\Columns\TextColumn::make('payment_request_number')->label('PR')->searchable(),
                Tables\Columns\TextColumn::make('source_type')->badge(),
                Tables\Columns\TextColumn::make('payee_name')->searchable(),
                Tables\Columns\TextColumn::make('bank_account_last4')->label('Acct')->formatStateUsing(fn ($state) => $state ? '•••• '.$state : '—'),
                Tables\Columns\TextColumn::make('amount')->money('IDR')->sortable(),
                Tables\Columns\IconColumn::make('withholding_required')->label('WHT')->boolean(),
                Tables\Columns\TextColumn::make('er_variance')->label('ER variance')->state(fn (PaymentRequest $r) => static::variance($r)),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')->color('success')->icon('heroicon-o-check')
                    ->requiresConfirmation()
                    ->modalDescription('Approving freezes an immutable business snapshot of this payment.')
                    ->action(fn (PaymentRequest $r) => static::run(fn () => app(PaymentRequestService::class)->approveAsCoo($r, Auth::user()), 'Payment request approved')),

                Tables\Actions\Action::make('reject')->color('danger')->icon('heroicon-o-x-mark')
                    ->form([Forms\Components\Textarea::make('reason')->required()])
                    ->action(fn (PaymentRequest $r, array $data) => static::run(fn () => app(PaymentRequestService::class)->rejectAsCoo($r, Auth::user(), $data['reason']), 'Payment request rejected')),

                Tables\Actions\Action::make('request_clarification')->icon('heroicon-o-question-mark-circle')
                    ->form([Forms\Components\Textarea::make('note')->required()])
                    ->action(fn (PaymentRequest $r, array $data) => static::run(fn () => app(PaymentRequestService::class)->requestClarification($r, Auth::user(), $data['note']), 'Clarification requested')),
            ]);
    }

    protected static function variance(PaymentRequest $r): string
    {
        $erAmount = $r->metadata['er_amount'] ?? null;
        if (! $erAmount) {
            return '—';
        }

        return 'ER '.$erAmount.(($r->metadata['variance_risk_flag'] ?? false) ? ' ⚠' : '');
    }

    protected static function run(callable $fn, string $message): void
    {
        try {
            $fn();
            Notification::make()->title($message)->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Action failed')->body($e->getMessage())->danger()->send();
        }
    }
}
