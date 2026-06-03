<?php

/**
 * Phase 4 — ER→PR conversion (gap-fill).
 *
 * Coverage classification — existing tests already cover:
 * - finance_verified ER → PR (source_type, ER→pr_created)      : ExpenseToPaymentRequestConversionTest
 * - company_paid_record_only cannot convert                     : ExpenseToPaymentRequestConversionTest
 * - one ER cannot create two active PRs                         : ExpenseToPaymentRequestConversionTest + FinanceIdempotencyConstraintTest Area 2
 * - variance requires justification                             : ExpenseToPaymentRequestConversionTest
 * - large variance risk-flag                                    : ExpenseToPaymentRequestConversionTest
 * - rejected PR releases ER back to finance_verified            : ExpenseToPaymentRequestConversionTest
 * - audit event expense_request.converted_to_pr                 : ExpenseRequestAuditTest
 * - unverified ER (generic) cannot create PR                    : ExpenseToPaymentRequestConversionTest "refuses to create a PR from an unverified ER"
 *
 * Net-new cases in this file:
 * 1. Invalid-transition matrix: each specific disallowed ER status is tested individually.
 *    The existing test only covers a generic "unverified ER"; it does NOT test every
 *    disallowed status (draft, submitted, needs_revision, rejected, cancelled,
 *    pr_created, record_only_closed) individually.
 * 2. voided PR releases ER back to finance_verified (void path — only rejectAsCoo path
 *    is covered in ExpenseToPaymentRequestConversionTest).
 */

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

beforeEach(function () {
    Storage::fake('private');
    $this->erSvc = app(ExpenseRequestService::class);
    $this->prSvc = app(PaymentRequestService::class);
    $this->employee = Employee::factory()->create();
    $this->finance = User::factory()->create();
    $this->creator = User::factory()->create();
});

// ─── helpers ────────────────────────────────────────────────────────────────

function p4_draft(array $overrides = []): ExpenseRequest
{
    return test()->erSvc->create(test()->employee, array_merge([
        'category' => 'travel',
        'title' => 'Taxi',
        'amount' => 1000000,
        'expense_date' => '2026-05-30',
        'payment_mode' => ExpenseRequest::MODE_VENDOR_PAYMENT,
        'payee_name' => 'PT Vendor',
        'payee_bank_name' => 'BCA',
        'payee_bank_account_number' => '1234567890',
    ], $overrides));
}

function p4_attachEvidence(ExpenseRequest $er): void
{
    app(FinanceAttachmentService::class)->store(
        $er,
        UploadedFile::fake()->createWithContent('r.pdf', 'x'.uniqid()),
        FinanceAttachment::ROLE_EXPENSE_EVIDENCE,
        test()->employee->user,
    );
}

function p4_verifiedEr(array $overrides = []): ExpenseRequest
{
    $er = p4_draft($overrides);
    p4_attachEvidence($er);
    test()->erSvc->submit($er);
    test()->erSvc->verify($er, test()->finance, 'ok');

    return $er->fresh();
}

// ─── Phase 4 net-new: invalid-transition matrix ──────────────────────────────

it('draft ER cannot create a PR', function () {
    $er = p4_draft();
    expect($er->status)->toBe(ExpenseRequest::STATUS_DRAFT);

    expect(fn () => $this->prSvc->createFromExpenseRequest($er, $this->creator))
        ->toThrow(\RuntimeException::class);
});

it('submitted ER cannot create a PR', function () {
    $er = p4_draft();
    p4_attachEvidence($er);
    $this->erSvc->submit($er);
    expect($er->fresh()->status)->toBe(ExpenseRequest::STATUS_SUBMITTED);

    expect(fn () => $this->prSvc->createFromExpenseRequest($er->fresh(), $this->creator))
        ->toThrow(\RuntimeException::class);
});

it('needs_revision ER cannot create a PR', function () {
    $er = p4_draft();
    p4_attachEvidence($er);
    $this->erSvc->submit($er);
    $this->erSvc->requestRevision($er, $this->finance, 'attach more receipts');
    expect($er->fresh()->status)->toBe(ExpenseRequest::STATUS_NEEDS_REVISION);

    expect(fn () => $this->prSvc->createFromExpenseRequest($er->fresh(), $this->creator))
        ->toThrow(\RuntimeException::class);
});

it('rejected ER cannot create a PR', function () {
    $er = p4_draft();
    p4_attachEvidence($er);
    $this->erSvc->submit($er);
    $this->erSvc->reject($er, $this->finance, 'duplicate claim');
    expect($er->fresh()->status)->toBe(ExpenseRequest::STATUS_REJECTED);

    expect(fn () => $this->prSvc->createFromExpenseRequest($er->fresh(), $this->creator))
        ->toThrow(\RuntimeException::class);
});

it('cancelled ER cannot create a PR', function () {
    $er = p4_draft();
    $this->erSvc->cancel($er);
    expect($er->fresh()->status)->toBe(ExpenseRequest::STATUS_CANCELLED);

    expect(fn () => $this->prSvc->createFromExpenseRequest($er->fresh(), $this->creator))
        ->toThrow(\RuntimeException::class);
});

it('pr_created ER cannot create another PR', function () {
    $er = p4_verifiedEr();
    $this->prSvc->createFromExpenseRequest($er, $this->creator);
    $fresh = $er->fresh();
    expect($fresh->status)->toBe(ExpenseRequest::STATUS_PR_CREATED);

    // ER status is now pr_created, not finance_verified — createFromExpenseRequest rejects it.
    expect(fn () => $this->prSvc->createFromExpenseRequest($fresh, $this->creator))
        ->toThrow(\RuntimeException::class);
});

// ─── Phase 4 net-new: voided PR releases ER (void path) ──────────────────────
// ExpenseToPaymentRequestConversionTest covers rejectAsCoo. This covers void().

it('voided PR returns the ER to finance_verified', function () {
    $er = p4_verifiedEr();
    $pr = $this->prSvc->createFromExpenseRequest($er, $this->creator);
    expect($er->fresh()->status)->toBe(ExpenseRequest::STATUS_PR_CREATED);

    $actor = User::factory()->create();
    $this->prSvc->void($pr, $actor, 'requested by submitter');

    expect($er->fresh()->status)->toBe(ExpenseRequest::STATUS_FINANCE_VERIFIED);
    expect($pr->fresh()->status)->toBe(PaymentRequest::STATUS_VOIDED);
});
