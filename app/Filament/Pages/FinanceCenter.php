<?php

namespace App\Filament\Pages;

use App\Models\Hris\ExpenseRequest;
use App\Models\Hris\FinanceAuditLog;
use App\Models\Hris\PaymentRequest;
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
        return ExpenseRequest::whereIn('status', [
            ExpenseRequest::STATUS_SUBMITTED,
            ExpenseRequest::STATUS_UNDER_FINANCE_REVIEW,
        ])->latest();
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

    /** Honest disabled states for batch payment sources not yet integrated (gated placeholders). */
    public static function gatedPlaceholders(): array
    {
        return [
            'payroll_payments' => 'Payroll Payment Requests are disabled until payroll calculation and approval stabilization is complete.',
            'weekly_allowance' => 'Weekly Allowance Payment Requests are disabled until WeeklyAllowanceBatch status/test issues are fixed.',
            'overtime_pay' => 'Overtime payment requests are disabled until batch payment integration is implemented.',
            'late_pay_compensation' => 'Late-pay payment requests are disabled until batch payment integration is implemented.',
        ];
    }
}
