<?php

namespace App\Filament\Pages;

use App\Models\Hris\ExpenseRequest;
use App\Models\Hris\FinanceAttachment;
use App\Models\Hris\FinanceAuditLog;
use App\Models\Hris\LatePayCompensationBatch;
use App\Models\Hris\OvertimePayBatch;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleItem;
use App\Models\Hris\PaymentRequest;
use App\Models\Hris\WeeklyAllowanceBatch;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/**
 * Finance Center workbench (EXPENSE_MODULE.MD Phase 11).
 * MVP-enabled: Inbox / ER review / PR queue / Paid-pending-reconciliation / Audit log.
 * Batch payment modules (payroll/allowance/overtime/late-pay) are honest gated placeholders.
 */
class FinanceCenter extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Finance Center';

    protected static ?string $title = 'Finance Center';

    protected static ?string $navigationGroup = 'Finance Center';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'finance-center';

    protected static string $view = 'filament.pages.finance-center';

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
            return $user->hasPermissionTo('finance_center.view', 'web');
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            return false;
        }
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function expenseReviewQueue()
    {
        return ExpenseRequest::with([
            'financeAttachments' => fn ($query) => $query
                ->where('attachment_role', FinanceAttachment::ROLE_EXPENSE_EVIDENCE)
                ->oldest('id'),
        ])->whereIn('status', [
            ExpenseRequest::STATUS_SUBMITTED,
            ExpenseRequest::STATUS_UNDER_FINANCE_REVIEW,
        ])->latest();
    }

    public function expenseEvidenceAttachment(ExpenseRequest $expenseRequest): ?FinanceAttachment
    {
        return $expenseRequest->financeAttachments->first()
            ?? $expenseRequest->financeAttachments()
                ->where('attachment_role', FinanceAttachment::ROLE_EXPENSE_EVIDENCE)
                ->oldest('id')
                ->first();
    }

    public function financeAttachmentViewUrl(FinanceAttachment $attachment): string
    {
        return route('finance.attachments.view', $attachment);
    }

    public function isImageFinanceAttachment(?FinanceAttachment $attachment): bool
    {
        return $attachment !== null && str_starts_with((string) $attachment->mime_type, 'image/');
    }

    public function paymentRequestQueue()
    {
        return PaymentRequest::whereIn('status', [
            PaymentRequest::STATUS_DRAFT,
            PaymentRequest::STATUS_SUBMITTED_TO_COO,
            PaymentRequest::STATUS_NEEDS_CLARIFICATION,
        ])->latest();
    }

    public function paidPendingReconciliationQueue()
    {
        return PaymentRequest::where('status', PaymentRequest::STATUS_PAID_PENDING_RECONCILIATION)->latest();
    }

    public function recentAuditLog()
    {
        return FinanceAuditLog::with('actor')->orderByDesc('id')->limit(20);
    }

    public function batchPaymentModules(): array
    {
        return [
            'weekly_allowance' => [
                'label' => 'Weekly Allowance Payments',
                'enabled' => true,
                'summary' => $this->batchSummary(WeeklyAllowanceBatch::class),
            ],
            'overtime_pay' => [
                'label' => 'Overtime Pay Payments',
                'enabled' => true,
                'summary' => $this->batchSummary(OvertimePayBatch::class),
            ],
            'late_pay_compensation' => [
                'label' => 'Late Pay Compensation Payments',
                'enabled' => true,
                'summary' => $this->batchSummary(LatePayCompensationBatch::class),
            ],
            'payroll_payments' => [
                'label' => 'Payroll Payments',
                'enabled' => false,
                'disabled_reason' => static::gatedPlaceholders()['payroll_payments'],
            ],
        ];
    }

    private function batchSummary(string $modelClass): array
    {
        return [
            'eligible' => $modelClass::where('payment_status', PaymentBundleItem::PAYMENT_STATUS_ELIGIBLE)->count(),
            'bundled' => $modelClass::where('payment_status', PaymentBundleItem::PAYMENT_STATUS_BUNDLED)->count(),
            'blocked' => $modelClass::where('payment_status', PaymentBundleItem::PAYMENT_STATUS_BLOCKED)->count(),
            'authorized' => PaymentBundle::whereHas('items', fn ($q) => $q
                ->where('source_type', (new $modelClass)->paymentSourceType())
                ->where('status', PaymentBundleItem::PAYMENT_STATUS_AUTHORIZED)
            )->count(),
            'recording' => PaymentBundle::whereHas('items', fn ($q) => $q
                ->where('source_type', (new $modelClass)->paymentSourceType())
                ->where('status', PaymentBundleItem::PAYMENT_STATUS_RECORDING)
            )->count(),
            'paid' => $modelClass::where('payment_status', PaymentBundleItem::PAYMENT_STATUS_PAID)->count(),
        ];
    }

    /** Honest disabled states for batch payment sources not yet integrated. */
    public static function gatedPlaceholders(): array
    {
        return [
            'payroll_payments' => 'Disabled until payroll batch-to-PaymentBundle integration has frozen payroll payee bank data and tax guard coverage.',
        ];
    }
}
