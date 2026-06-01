<?php

use App\Filament\Pages\FinanceCenter;
use App\Models\Hris\ExpenseRequest;
use App\Models\Hris\PaymentRequest;
use App\Models\User;
use Database\Seeders\FinanceRolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;

/**
 * Phase 11 — Finance Center access gating + queue scoping (EXPENSE_MODULE.MD).
 */
beforeEach(function () {
    $this->seed(FinanceRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('grants Finance Center access to a user with finance_center.view', function () {
    $user = User::factory()->create();
    $user->assignRole('finance_reviewer');
    actingAs($user);

    expect(FinanceCenter::canAccess())->toBeTrue();
});

it('denies Finance Center access to a user without finance permission', function () {
    actingAs(User::factory()->create());

    expect(FinanceCenter::canAccess())->toBeFalse();
});

it('scopes the expense-review queue to ERs awaiting finance action', function () {
    makeEr(ExpenseRequest::STATUS_SUBMITTED);
    makeEr(ExpenseRequest::STATUS_UNDER_FINANCE_REVIEW);
    makeEr(ExpenseRequest::STATUS_DRAFT);       // not in queue
    makeEr(ExpenseRequest::STATUS_FINANCE_VERIFIED); // not in queue

    expect((new FinanceCenter)->expenseReviewQueue()->count())->toBe(2);
});

it('scopes the paid-pending-reconciliation queue', function () {
    makePr(PaymentRequest::STATUS_PAID_PENDING_RECONCILIATION);
    makePr(PaymentRequest::STATUS_PAID_PENDING_RECONCILIATION);
    makePr(PaymentRequest::STATUS_APPROVED_BY_COO); // not in queue

    expect((new FinanceCenter)->paidPendingReconciliationQueue()->count())->toBe(2);
});

it('lists the gated placeholder modules with honest disabled reasons', function () {
    $gated = FinanceCenter::gatedPlaceholders();

    expect($gated)->toHaveKeys(['payroll_payments', 'weekly_allowance', 'overtime_pay', 'late_pay_compensation']);
    expect($gated['weekly_allowance'])->toBeString()->not->toBeEmpty();
});

function makeEr(string $status): ExpenseRequest
{
    $employee = \App\Models\Hris\Employee::factory()->create();

    return ExpenseRequest::create([
        'request_number' => 'ER-TEST-'.uniqid(),
        'employee_id' => $employee->id,
        'category' => 'x', 'title' => 't', 'amount' => 1000, 'currency' => 'IDR',
        'expense_date' => '2026-05-30', 'payment_mode' => ExpenseRequest::MODE_EMPLOYEE_REIMBURSEMENT,
        'status' => $status,
    ]);
}

function makePr(string $status): PaymentRequest
{
    return PaymentRequest::create([
        'payment_request_number' => 'PR-TEST-'.uniqid(),
        'source_type' => PaymentRequest::SOURCE_MANUAL,
        'created_by' => User::factory()->create()->id,
        'payee_name' => 'V', 'amount' => 1000, 'currency' => 'IDR',
        'status' => $status,
    ]);
}
