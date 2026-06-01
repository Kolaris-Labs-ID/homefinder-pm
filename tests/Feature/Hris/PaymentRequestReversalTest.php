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
 * Phase 9 — void (pre-payment) + reversal (post-payment) (EXPENSE_MODULE.MD).
 */
beforeEach(function () {
    Storage::fake('private');
    $this->service = app(PaymentRequestService::class);
    $this->actor = User::factory()->create();
    $this->coo = User::factory()->create();
});

function manualPr(): PaymentRequest
{
    return test()->service->createManual(User::factory()->create(), [
        'payee_type' => 'vendor', 'payee_name' => 'PT V', 'bank_name' => 'BCA',
        'bank_account_number' => '1234567890', 'amount' => 1000000, 'currency' => 'IDR',
    ]);
}

it('voids a pre-payment PR and returns its ER to finance_verified', function () {
    $employee = Employee::factory()->create();
    $erSvc = app(ExpenseRequestService::class);
    $er = $erSvc->create($employee, [
        'category' => 'x', 'title' => 't', 'amount' => 1000000, 'expense_date' => '2026-05-30',
        'payment_mode' => ExpenseRequest::MODE_VENDOR_PAYMENT, 'payee_name' => 'PT V',
        'payee_bank_name' => 'BCA', 'payee_bank_account_number' => '1234567890',
    ]);
    app(FinanceAttachmentService::class)->store($er, UploadedFile::fake()->createWithContent('r.pdf', 'r'), FinanceAttachment::ROLE_EXPENSE_EVIDENCE, $employee->user);
    $erSvc->submit($er);
    $erSvc->verify($er, $this->actor, 'ok');
    $pr = $this->service->createFromExpenseRequest($er->fresh(), $this->actor);

    $this->service->void($pr, $this->actor, 'created in error');

    expect($pr->fresh()->status)->toBe(PaymentRequest::STATUS_VOIDED);
    expect($pr->fresh()->voided_by)->toBe($this->actor->id);
    expect($er->fresh()->status)->toBe(ExpenseRequest::STATUS_FINANCE_VERIFIED);
});

it('reverses a paid PR', function () {
    $pr = manualPr();
    $this->service->submitToCoo($pr);
    $this->service->approveAsCoo($pr, $this->coo);
    $proof = app(FinanceAttachmentService::class)->store($pr, UploadedFile::fake()->createWithContent('p.pdf', 'p'), FinanceAttachment::ROLE_PAYMENT_PROOF, $this->actor);
    $this->service->markPaid($pr, $this->actor, 'TRX', $proof);

    $this->service->reverse($pr->fresh(), $this->actor, 'wrong account');

    expect($pr->fresh()->status)->toBe(PaymentRequest::STATUS_REVERSED);
    expect($pr->fresh()->reversed_by)->toBe($this->actor->id);
});

it('cannot void a PR that has already been paid', function () {
    $pr = manualPr();
    $this->service->submitToCoo($pr);
    $this->service->approveAsCoo($pr, $this->coo);
    $proof = app(FinanceAttachmentService::class)->store($pr, UploadedFile::fake()->createWithContent('p.pdf', 'p'), FinanceAttachment::ROLE_PAYMENT_PROOF, $this->actor);
    $this->service->markPaid($pr, $this->actor, 'TRX', $proof);

    expect(fn () => $this->service->void($pr->fresh(), $this->actor, 'too late'))->toThrow(RuntimeException::class);
});
