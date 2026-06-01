<?php

use App\Models\Hris\FinanceAttachment;
use App\Models\Hris\PaymentRequest;
use App\Models\User;
use App\Services\Hris\FinanceAttachmentService;
use App\Services\Hris\PaymentRequestService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Phase 9 — manual reconciliation (EXPENSE_MODULE.MD).
 */
beforeEach(function () {
    Storage::fake('private');
    $this->service = app(PaymentRequestService::class);
    $this->coo = User::factory()->create();
    $this->executor = User::factory()->create();
    $this->reconciler = User::factory()->create();
});

function approvedHighValuePr(): PaymentRequest
{
    $pr = test()->service->createManual(User::factory()->create(), [
        'payee_type' => 'vendor', 'payee_name' => 'PT Vendor', 'bank_name' => 'BCA',
        'bank_account_number' => '1234567890', 'amount' => 50000000, 'currency' => 'IDR',
    ]);
    test()->service->submitToCoo($pr);
    test()->service->approveAsCoo($pr, test()->coo);

    return $pr->fresh();
}

function payProof(PaymentRequest $pr, User $executor): FinanceAttachment
{
    return app(FinanceAttachmentService::class)->store(
        $pr, UploadedFile::fake()->createWithContent('proof.pdf', 'transfer'.uniqid()),
        FinanceAttachment::ROLE_PAYMENT_PROOF, $executor
    );
}

it('marks paid_pending_reconciliation (not reconciled) when the executor uploads proof', function () {
    $pr = approvedHighValuePr();
    $proof = payProof($pr, $this->executor);

    $this->service->markPaid($pr, $this->executor, 'TRX-1', $proof);

    $fresh = $pr->fresh();
    expect($fresh->status)->toBe(PaymentRequest::STATUS_PAID_PENDING_RECONCILIATION);
    expect($fresh->paid_by)->toBe($this->executor->id);
    expect($fresh->payment_proof_attachment_id)->toBe($proof->id);
});

it('lets a different reconciler reconcile a high-value payment', function () {
    $pr = approvedHighValuePr();
    $this->service->markPaid($pr, $this->executor, 'TRX-1', payProof($pr, $this->executor));

    $this->service->reconcile($pr->fresh(), $this->reconciler, [
        'amount' => '50000000.00', 'payee_name' => 'PT Vendor', 'bank_account_last4' => '7890', 'reference' => 'BANK-REF',
    ]);

    $fresh = $pr->fresh();
    expect($fresh->status)->toBe(PaymentRequest::STATUS_RECONCILED);
    expect($fresh->reconciled_by)->toBe($this->reconciler->id);
});

it('forbids the executor reconciling their own high-value payment', function () {
    $pr = approvedHighValuePr();
    $this->service->markPaid($pr, $this->executor, 'TRX-1', payProof($pr, $this->executor));

    expect(fn () => $this->service->reconcile($pr->fresh(), $this->executor, [
        'amount' => '50000000.00', 'payee_name' => 'PT Vendor', 'bank_account_last4' => '7890', 'reference' => 'BANK-REF',
    ]))->toThrow(RuntimeException::class);
});

it('fails reconciliation on an amount mismatch', function () {
    $pr = approvedHighValuePr();
    $this->service->markPaid($pr, $this->executor, 'TRX-1', payProof($pr, $this->executor));

    $this->service->reconcile($pr->fresh(), $this->reconciler, [
        'amount' => '49000000.00', 'payee_name' => 'PT Vendor', 'bank_account_last4' => '7890', 'reference' => 'BANK-REF',
    ]);

    expect($pr->fresh()->status)->toBe(PaymentRequest::STATUS_RECONCILIATION_FAILED);
});
