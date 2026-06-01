<?php

use App\Models\Hris\Employee;
use App\Models\Hris\ExpenseRequest;
use App\Models\Hris\FinanceAttachment;
use App\Models\User;
use App\Services\Hris\ExpenseRequestService;
use App\Services\Hris\FinanceAttachmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Phase 4 — ExpenseRequest model & lifecycle (EXPENSE_MODULE.MD).
 */
beforeEach(function () {
    Storage::fake('private');
    $this->service = app(ExpenseRequestService::class);
    $this->attachments = app(FinanceAttachmentService::class);
    $this->employee = Employee::factory()->create();
    $this->finance = User::factory()->create();
});

function makeErDraft(array $overrides = []): ExpenseRequest
{
    return test()->service->create(test()->employee, array_merge([
        'category' => 'travel',
        'title' => 'Client visit taxi',
        'description' => 'Grab to client office',
        'amount' => 150000,
        'expense_date' => '2026-05-30',
        'payment_mode' => ExpenseRequest::MODE_EMPLOYEE_REIMBURSEMENT,
    ], $overrides));
}

function attachErEvidence(ExpenseRequest $er, string $content = 'receipt'): FinanceAttachment
{
    return test()->attachments->store(
        $er,
        UploadedFile::fake()->createWithContent('receipt.pdf', $content),
        FinanceAttachment::ROLE_EXPENSE_EVIDENCE,
        test()->employee->user,
    );
}

it('creates a draft with a generated ER number and decimal-safe amount', function () {
    $er = makeErDraft();

    expect($er->status)->toBe(ExpenseRequest::STATUS_DRAFT);
    expect($er->request_number)->toStartWith('ER-'.now()->format('Ym').'-');
    expect($er->employee_id)->toBe($this->employee->id);
    expect($er->amount)->toBe('150000.00'); // string cast — never float
});

it('generates unique ER numbers', function () {
    expect(makeErDraft()->request_number)->not->toBe(makeErDraft()->request_number);
});

it('refuses to submit an ER without evidence', function () {
    $er = makeErDraft();
    expect(fn () => $this->service->submit($er))->toThrow(RuntimeException::class);
    expect($er->fresh()->status)->toBe(ExpenseRequest::STATUS_DRAFT);
});

it('submits an ER once evidence is attached', function () {
    $er = makeErDraft();
    attachErEvidence($er);

    $this->service->submit($er);

    expect($er->fresh()->status)->toBe(ExpenseRequest::STATUS_SUBMITTED);
    expect($er->fresh()->submitted_at)->not->toBeNull();
});

it('lets finance verify a submitted ER', function () {
    $er = makeErDraft();
    attachErEvidence($er);
    $this->service->submit($er);

    $this->service->verify($er, $this->finance, 'looks good');

    expect($er->fresh()->status)->toBe(ExpenseRequest::STATUS_FINANCE_VERIFIED);
    expect($er->fresh()->finance_reviewed_by)->toBe($this->finance->id);
    expect($er->fresh()->finance_reviewed_at)->not->toBeNull();
});

it('lets finance reject a submitted ER with a reason', function () {
    $er = makeErDraft();
    attachErEvidence($er);
    $this->service->submit($er);

    $this->service->reject($er, $this->finance, 'duplicate claim');

    expect($er->fresh()->status)->toBe(ExpenseRequest::STATUS_REJECTED);
    expect($er->fresh()->rejection_reason)->toBe('duplicate claim');
});

it('lets finance request a revision', function () {
    $er = makeErDraft();
    attachErEvidence($er);
    $this->service->submit($er);

    $this->service->requestRevision($er, $this->finance, 'attach itemised receipt');

    expect($er->fresh()->status)->toBe(ExpenseRequest::STATUS_NEEDS_REVISION);
    expect($er->fresh()->revision_requested_at)->not->toBeNull();
});

it('allows revision only when needs_revision, and resets finance verification', function () {
    $er = makeErDraft();
    attachErEvidence($er);
    $this->service->submit($er);

    expect(fn () => $this->service->revise($er, ['amount' => 200000]))->toThrow(RuntimeException::class);

    $this->service->requestRevision($er, $this->finance, 'fix amount');
    $this->service->revise($er, ['amount' => 200000]);

    $fresh = $er->fresh();
    expect($fresh->status)->toBe(ExpenseRequest::STATUS_SUBMITTED);
    expect($fresh->amount)->toBe('200000.00');
    expect($fresh->finance_reviewed_by)->toBeNull();
    expect($fresh->finance_reviewed_at)->toBeNull();
});

it('resets finance verification when a verified ER amount changes', function () {
    $er = makeErDraft();
    attachErEvidence($er);
    $this->service->submit($er);
    $this->service->verify($er, $this->finance, 'ok');

    $this->service->updateDetails($er, ['amount' => 999000]);

    $fresh = $er->fresh();
    expect($fresh->status)->toBe(ExpenseRequest::STATUS_SUBMITTED);
    expect($fresh->finance_reviewed_by)->toBeNull();
});

it('closes a company_paid_record_only ER without a payment request', function () {
    $er = makeErDraft(['payment_mode' => ExpenseRequest::MODE_COMPANY_PAID_RECORD_ONLY]);
    attachErEvidence($er);
    $this->service->submit($er);
    $this->service->verify($er, $this->finance, 'recorded');

    $this->service->closeAsRecordOnly($er);

    expect($er->fresh()->status)->toBe(ExpenseRequest::STATUS_RECORD_ONLY_CLOSED);
    expect($er->fresh()->closed_at)->not->toBeNull();
});

it('encrypts the payee bank account number and stores only last4 in clear', function () {
    $er = makeErDraft([
        'payment_mode' => ExpenseRequest::MODE_VENDOR_PAYMENT,
        'payee_name' => 'PT Vendor',
        'payee_bank_name' => 'BCA',
        'payee_bank_account_number' => '1234567890',
    ]);

    expect($er->payee_bank_account_last4)->toBe('7890');
    expect($er->payee_bank_account_number)->toBe('1234567890'); // decrypted accessor
    $raw = DB::table('expense_requests')->where('id', $er->id)->value('payee_bank_account_number_encrypted');
    expect($raw)->not->toBe('1234567890');
});
