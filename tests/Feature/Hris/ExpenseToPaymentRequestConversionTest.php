<?php

use App\Models\Hris\Employee;
use App\Models\Hris\ExpenseRequest;
use App\Models\Hris\FinanceAttachment;
use App\Models\Hris\PaymentRequest;
use App\Models\User;
use App\Services\Hris\ExpenseRequestService;
use App\Services\Hris\FinanceAttachmentService;
use App\Services\Hris\PaymentRequestService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Phase 7 — ER → PR conversion integrity (EXPENSE_MODULE.MD).
 */
beforeEach(function () {
    Storage::fake('private');
    $this->er = app(ExpenseRequestService::class);
    $this->pr = app(PaymentRequestService::class);
    $this->employee = Employee::factory()->create();
    $this->finance = User::factory()->create();
    $this->creator = User::factory()->create();
});

function verifiedEr(array $overrides = []): ExpenseRequest
{
    $er = test()->er->create(test()->employee, array_merge([
        'category' => 'travel', 'title' => 'Taxi', 'amount' => 1000000, 'expense_date' => '2026-05-30',
        'payment_mode' => ExpenseRequest::MODE_VENDOR_PAYMENT,
        'payee_name' => 'PT Vendor', 'payee_bank_name' => 'BCA', 'payee_bank_account_number' => '1234567890',
    ], $overrides));

    app(FinanceAttachmentService::class)->store(
        $er,
        UploadedFile::fake()->createWithContent('r.pdf', 'receipt'.uniqid()),
        FinanceAttachment::ROLE_EXPENSE_EVIDENCE,
        test()->employee->user,
    );
    test()->er->submit($er);
    test()->er->verify($er, test()->finance, 'ok');

    return $er->fresh();
}

it('creates a PR from a verified ER, defaulting payee + bank from the ER', function () {
    $er = verifiedEr();

    $pr = $this->pr->createFromExpenseRequest($er, $this->creator);

    expect($pr->source_type)->toBe(PaymentRequest::SOURCE_EXPENSE_REQUEST);
    expect($pr->source_id)->toBe($er->id);
    expect($pr->amount)->toBe('1000000.00');
    expect($pr->payee_name)->toBe('PT Vendor');
    expect($pr->bank_account_last4)->toBe('7890');
    expect($er->fresh()->status)->toBe(ExpenseRequest::STATUS_PR_CREATED);
});

it('resolves the payee from the employee profile for an employee reimbursement with blank payee fields', function () {
    $this->employee->update([
        'bank_name' => 'BCA',
        'bank_account_number' => '9998887776',
        'bank_account_holder' => 'Andi Reimburse',
    ]);

    $er = verifiedEr([
        'payment_mode' => ExpenseRequest::MODE_EMPLOYEE_REIMBURSEMENT,
        'payee_name' => null, 'payee_bank_name' => null, 'payee_bank_account_number' => null,
    ]);

    $pr = $this->pr->createFromExpenseRequest($er, $this->creator);

    expect($pr->payee_name)->toBe('Andi Reimburse');
    expect($pr->bank_name)->toBe('BCA');
    expect($pr->bank_account_last4)->toBe('7776');
    expect($pr->bank_account_number)->toBe('9998887776');
});

it('falls back to the employee user name when the reimbursement employee has no bank holder', function () {
    $this->employee->user->update(['name' => 'Budi Employee']);

    $er = verifiedEr([
        'payment_mode' => ExpenseRequest::MODE_EMPLOYEE_REIMBURSEMENT,
        'payee_name' => null, 'payee_bank_name' => null, 'payee_bank_account_number' => null,
    ]);

    $pr = $this->pr->createFromExpenseRequest($er, $this->creator);

    expect($pr->payee_name)->toBe('Budi Employee');
});

it('rejects conversion with a clear error when no payee can be resolved', function () {
    $er = verifiedEr([
        'payment_mode' => ExpenseRequest::MODE_VENDOR_PAYMENT,
        'payee_name' => null, 'payee_bank_name' => null, 'payee_bank_account_number' => null,
    ]);

    expect(fn () => $this->pr->createFromExpenseRequest($er, $this->creator))
        ->toThrow(RuntimeException::class);
});

it('refuses to create a PR from an unverified ER', function () {
    $er = test()->er->create($this->employee, [
        'category' => 'travel', 'title' => 'Taxi', 'amount' => 1000000, 'expense_date' => '2026-05-30',
        'payment_mode' => ExpenseRequest::MODE_VENDOR_PAYMENT,
    ]);

    expect(fn () => $this->pr->createFromExpenseRequest($er, $this->creator))->toThrow(RuntimeException::class);
});

it('refuses to create a PR from a company_paid_record_only ER', function () {
    $er = verifiedEr(['payment_mode' => ExpenseRequest::MODE_COMPANY_PAID_RECORD_ONLY]);

    expect(fn () => $this->pr->createFromExpenseRequest($er, $this->creator))->toThrow(RuntimeException::class);
});

it('creates only one active PR per ER (double-submit guard)', function () {
    $er = verifiedEr();
    $this->pr->createFromExpenseRequest($er, $this->creator);

    expect(fn () => $this->pr->createFromExpenseRequest($er->fresh(), $this->creator))->toThrow(RuntimeException::class);
    expect(PaymentRequest::where('source_id', $er->id)->count())->toBe(1);
});

it('requires a justification when the PR amount differs from the ER', function () {
    $er = verifiedEr();

    expect(fn () => $this->pr->createFromExpenseRequest($er, $this->creator, ['amount' => 1200000]))
        ->toThrow(RuntimeException::class);

    $pr = $this->pr->createFromExpenseRequest($er->fresh(), $this->creator, ['amount' => 1200000, 'variance_reason' => 'extra toll']);
    expect($pr->amount)->toBe('1200000.00');
    expect($pr->metadata['variance_reason'])->toBe('extra toll');
});

it('risk-flags a large amount variance', function () {
    $er = verifiedEr();

    $pr = $this->pr->createFromExpenseRequest($er, $this->creator, ['amount' => 5000000, 'variance_reason' => 'bulk']);

    expect($pr->metadata['variance_risk_flag'])->toBeTrue();
});

it('returns the ER to finance_verified when its PR is rejected by the COO', function () {
    $er = verifiedEr();
    $pr = $this->pr->createFromExpenseRequest($er, $this->creator);
    $this->pr->submitToCoo($pr);

    $this->pr->rejectAsCoo($pr, User::factory()->create(), 'no budget');

    expect($er->fresh()->status)->toBe(ExpenseRequest::STATUS_FINANCE_VERIFIED);
});
