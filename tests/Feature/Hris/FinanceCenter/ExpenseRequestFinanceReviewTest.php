<?php

/**
 * Phase 3 — ER finance review (gap-fill).
 *
 * Coverage classification — existing tests already cover:
 * - verify / reject / requestRevision happy path       : ExpenseRequestLifecycleTest
 * - reason required for reject / requestRevision       : ExpenseRequestLifecycleTest + AuditTest
 * - employee revise + resubmit                         : ExpenseRequestLifecycleTest / ApiTest
 * - non-finance user cannot verify/reject (Filament)   : ExpenseRequestResourceActionAuthorizationTest (Phase 15)
 * - amount change after verify resets verification     : ExpenseRequestLifecycleTest "resets finance verification when a verified ER amount changes"
 * - all audit events                                   : ExpenseRequestAuditTest
 *
 * Net-new cases in this file:
 * 1. All four SENSITIVE fields individually trigger verification reset (payee_name,
 *    payee_bank_name, payee_bank_account_number — amount is already covered).
 *    Non-sensitive field change does NOT reset.
 * 2. Illegal-transition block-list at service layer.
 *
 * BUG-4 FIXED: ExpenseRequestService.verify(), reject(), requestRevision() now guard
 * against invalid source states, mirroring canReview() (submitted | under_finance_review).
 */

use App\Models\Hris\Employee;
use App\Models\Hris\ExpenseRequest;
use App\Models\Hris\FinanceAttachment;
use App\Models\User;
use App\Services\Hris\ExpenseRequestService;
use App\Services\Hris\FinanceAttachmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('private');
    $this->svc = app(ExpenseRequestService::class);
    $this->employee = Employee::factory()->create();
    $this->finance = User::factory()->create();
});

// ─── helpers ────────────────────────────────────────────────────────────────

function p3_draft(array $overrides = []): ExpenseRequest
{
    return test()->svc->create(test()->employee, array_merge([
        'category' => 'travel',
        'title' => 'Taxi',
        'amount' => 150000,
        'expense_date' => '2026-05-30',
        'payment_mode' => ExpenseRequest::MODE_VENDOR_PAYMENT,
        'payee_name' => 'PT Vendor',
        'payee_bank_name' => 'BCA',
        'payee_bank_account_number' => '1234567890',
    ], $overrides));
}

function p3_attachEvidence(ExpenseRequest $er): void
{
    app(FinanceAttachmentService::class)->store(
        $er,
        UploadedFile::fake()->createWithContent('r.pdf', 'x'.uniqid()),
        FinanceAttachment::ROLE_EXPENSE_EVIDENCE,
        test()->employee->user,
    );
}

function p3_verifiedEr(array $overrides = []): ExpenseRequest
{
    $er = p3_draft($overrides);
    p3_attachEvidence($er);
    test()->svc->submit($er);
    test()->svc->verify($er, test()->finance, 'ok');

    return $er->fresh();
}

// ─── Phase 3 net-new: all SENSITIVE fields reset verification ────────────────

it('changing payee_name after verify resets to submitted', function () {
    $er = p3_verifiedEr();
    expect($er->status)->toBe(ExpenseRequest::STATUS_FINANCE_VERIFIED);

    $this->svc->updateDetails($er, ['payee_name' => 'PT Other Vendor']);

    $fresh = $er->fresh();
    expect($fresh->status)->toBe(ExpenseRequest::STATUS_SUBMITTED);
    expect($fresh->finance_reviewed_by)->toBeNull();
    expect($fresh->finance_reviewed_at)->toBeNull();
});

it('changing payee_bank_name after verify resets to submitted', function () {
    $er = p3_verifiedEr();

    $this->svc->updateDetails($er, ['payee_bank_name' => 'Mandiri']);

    $fresh = $er->fresh();
    expect($fresh->status)->toBe(ExpenseRequest::STATUS_SUBMITTED);
    expect($fresh->finance_reviewed_by)->toBeNull();
});

it('changing payee_bank_account_number after verify resets to submitted and updates last4', function () {
    $er = p3_verifiedEr();

    $this->svc->updateDetails($er, ['payee_bank_account_number' => '9876543210']);

    $fresh = $er->fresh();
    expect($fresh->status)->toBe(ExpenseRequest::STATUS_SUBMITTED);
    expect($fresh->finance_reviewed_by)->toBeNull();
    expect($fresh->payee_bank_account_last4)->toBe('3210');
});

it('changing a non-sensitive field (description) after verify does NOT reset verification', function () {
    $er = p3_verifiedEr();

    $this->svc->updateDetails($er, ['description' => 'Updated note']);

    $fresh = $er->fresh();
    expect($fresh->status)->toBe(ExpenseRequest::STATUS_FINANCE_VERIFIED);
    expect($fresh->finance_reviewed_by)->not->toBeNull();
});

// ─── Phase 3 net-new: illegal-transition block-list (BUG-4 fixed) ────────────

it('verify throws when ER is already finance_verified', function () {
    $er = p3_verifiedEr();

    expect(fn () => $this->svc->verify($er, $this->finance, 'again'))
        ->toThrow(\RuntimeException::class, 'Only a submitted expense request can be verified.');
});

it('verify throws when ER is rejected', function () {
    $er = p3_draft();
    p3_attachEvidence($er);
    $this->svc->submit($er);
    $this->svc->reject($er, $this->finance, 'duplicate');

    expect(fn () => $this->svc->verify($er->fresh(), $this->finance, 'override'))
        ->toThrow(\RuntimeException::class, 'Only a submitted expense request can be verified.');
});

it('verify throws when ER is cancelled', function () {
    $er = p3_draft();
    $this->svc->cancel($er);

    expect(fn () => $this->svc->verify($er->fresh(), $this->finance, 'notes'))
        ->toThrow(\RuntimeException::class, 'Only a submitted expense request can be verified.');
});

it('verify throws when ER is record_only_closed', function () {
    $er = p3_draft(['payment_mode' => ExpenseRequest::MODE_COMPANY_PAID_RECORD_ONLY]);
    p3_attachEvidence($er);
    $this->svc->submit($er);
    $this->svc->verify($er, $this->finance, 'recorded');
    $this->svc->closeAsRecordOnly($er);

    expect(fn () => $this->svc->verify($er->fresh(), $this->finance, 're-verify'))
        ->toThrow(\RuntimeException::class, 'Only a submitted expense request can be verified.');
});

it('requestRevision throws when ER is rejected', function () {
    $er = p3_draft();
    p3_attachEvidence($er);
    $this->svc->submit($er);
    $this->svc->reject($er, $this->finance, 'invalid');

    expect(fn () => $this->svc->requestRevision($er->fresh(), $this->finance, 'please fix'))
        ->toThrow(\RuntimeException::class, 'Only a submitted expense request can have a revision requested.');
});
