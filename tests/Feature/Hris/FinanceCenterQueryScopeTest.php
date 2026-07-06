<?php

use App\Filament\Pages\FinanceCenter;
use App\Models\Hris\ExpenseRequest;
use App\Models\Hris\FinanceAttachment;
use App\Models\Hris\PaymentRequest;
use App\Models\User;
use App\Services\Hris\FinanceAttachmentService;
use Database\Seeders\FinanceRolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
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
    financeCenterEr(ExpenseRequest::STATUS_SUBMITTED);
    financeCenterEr(ExpenseRequest::STATUS_UNDER_FINANCE_REVIEW);
    financeCenterEr(ExpenseRequest::STATUS_DRAFT);       // not in queue
    financeCenterEr(ExpenseRequest::STATUS_FINANCE_VERIFIED); // not in queue

    expect((new FinanceCenter)->expenseReviewQueue()->count())->toBe(2);
});

it('excludes draft ERs from the finance center expense-request listing', function () {
    $submitted = financeCenterEr(ExpenseRequest::STATUS_SUBMITTED);
    $draft = financeCenterEr(ExpenseRequest::STATUS_DRAFT);

    $ids = \App\Filament\Resources\ExpenseRequestResource::getEloquentQuery()->pluck('id')->all();

    expect($ids)->toContain($submitted->id);
    expect($ids)->not->toContain($draft->id);
});

it('renders the ER evidence photo preview in the expense review inbox', function () {
    Storage::fake('private');
    $reviewer = User::factory()->create();
    $reviewer->assignRole('finance_reviewer');
    $er = financeCenterEr(ExpenseRequest::STATUS_SUBMITTED);
    $attachment = app(FinanceAttachmentService::class)->store(
        $er,
        UploadedFile::fake()->create('receipt.jpg', 4, 'image/jpeg'),
        FinanceAttachment::ROLE_EXPENSE_EVIDENCE,
        $er->employee->user,
    );

    Livewire::actingAs($reviewer)
        ->test(FinanceCenter::class)
        ->assertSee("/admin/finance-attachments/{$attachment->id}/view", false)
        ->assertSee('openEvidence', false)
        ->assertDontSee("href=\"http://localhost/admin/finance-attachments/{$attachment->id}/view\"", false)
        ->assertDontSee("href=\"/admin/finance-attachments/{$attachment->id}/view\"", false);
});

it('scopes the paid-pending-reconciliation queue', function () {
    financeCenterPr(PaymentRequest::STATUS_PAID_PENDING_RECONCILIATION);
    financeCenterPr(PaymentRequest::STATUS_PAID_PENDING_RECONCILIATION);
    financeCenterPr(PaymentRequest::STATUS_APPROVED_BY_COO); // not in queue

    expect((new FinanceCenter)->paidPendingReconciliationQueue()->count())->toBe(2);
});

it('enables completed batch modules and keeps payroll honestly disabled', function () {
    $modules = (new FinanceCenter)->batchPaymentModules();

    expect($modules)->toHaveKeys(['payroll_payments', 'weekly_allowance', 'overtime_pay', 'late_pay_compensation']);
    expect($modules['weekly_allowance']['enabled'])->toBeTrue();
    expect($modules['overtime_pay']['enabled'])->toBeTrue();
    expect($modules['late_pay_compensation']['enabled'])->toBeTrue();
    expect($modules['payroll_payments']['enabled'])->toBeFalse();
    expect($modules['payroll_payments']['disabled_reason'])->toContain('payroll batch-to-PaymentBundle');
});

function financeCenterEr(string $status): ExpenseRequest
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

function financeCenterPr(string $status): PaymentRequest
{
    return PaymentRequest::create([
        'payment_request_number' => 'PR-TEST-'.uniqid(),
        'source_type' => PaymentRequest::SOURCE_MANUAL,
        'created_by' => User::factory()->create()->id,
        'payee_name' => 'V', 'amount' => 1000, 'currency' => 'IDR',
        'status' => $status,
    ]);
}
