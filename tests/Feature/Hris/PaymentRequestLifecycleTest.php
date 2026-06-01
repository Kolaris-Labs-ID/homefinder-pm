<?php

use App\Models\Hris\PaymentRequest;
use App\Models\User;
use App\Services\Hris\PaymentRequestService;
use Illuminate\Support\Facades\DB;

/**
 * Phase 5 — PaymentRequest lifecycle (EXPENSE_MODULE.MD).
 */
beforeEach(function () {
    $this->service = app(PaymentRequestService::class);
    $this->creator = User::factory()->create();
    $this->coo = User::factory()->create();
});

function makePr(array $overrides = []): PaymentRequest
{
    return test()->service->createManual(test()->creator, array_merge([
        'payee_type' => 'vendor',
        'payee_name' => 'PT Vendor',
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'amount' => 5000000,
        'currency' => 'IDR',
        'description' => 'Office supplies',
    ], $overrides));
}

it('creates a manual PR as draft with a generated PR number', function () {
    $pr = makePr();

    expect($pr->status)->toBe(PaymentRequest::STATUS_DRAFT);
    expect($pr->payment_request_number)->toStartWith('PR-'.now()->format('Ym').'-');
    expect($pr->source_type)->toBe(PaymentRequest::SOURCE_MANUAL);
    expect($pr->amount)->toBe('5000000.00'); // string — never float
    expect($pr->created_by)->toBe($this->creator->id);
});

it('encrypts the PR bank account and stores last4 in clear', function () {
    $pr = makePr(['bank_account_number' => '9876543210']);

    expect($pr->bank_account_last4)->toBe('3210');
    expect($pr->bank_account_number)->toBe('9876543210'); // decrypted accessor
    $raw = DB::table('payment_requests')->where('id', $pr->id)->value('bank_account_number_encrypted');
    expect($raw)->not->toBe('9876543210');
});

it('submits a draft PR to the COO', function () {
    $pr = makePr();

    $this->service->submitToCoo($pr);

    expect($pr->fresh()->status)->toBe(PaymentRequest::STATUS_SUBMITTED_TO_COO);
    expect($pr->fresh()->submitted_to_coo_at)->not->toBeNull();
});

it('lets the COO approve a submitted PR', function () {
    $pr = makePr();
    $this->service->submitToCoo($pr);

    $this->service->approveAsCoo($pr, $this->coo);

    $fresh = $pr->fresh();
    expect($fresh->status)->toBe(PaymentRequest::STATUS_APPROVED_BY_COO);
    expect($fresh->coo_approved_by)->toBe($this->coo->id);
    expect($fresh->coo_approved_at)->not->toBeNull();
});

it('lets the COO reject a submitted PR with a reason', function () {
    $pr = makePr();
    $this->service->submitToCoo($pr);

    $this->service->rejectAsCoo($pr, $this->coo, 'no budget');

    expect($pr->fresh()->status)->toBe(PaymentRequest::STATUS_REJECTED_BY_COO);
    expect($pr->fresh()->coo_rejection_reason)->toBe('no budget');
});

it('lets the COO request clarification', function () {
    $pr = makePr();
    $this->service->submitToCoo($pr);

    $this->service->requestClarification($pr, $this->coo, 'which cost centre?');

    expect($pr->fresh()->status)->toBe(PaymentRequest::STATUS_NEEDS_CLARIFICATION);
});

it('cannot approve a PR that was not submitted', function () {
    $pr = makePr(); // still draft
    expect(fn () => $this->service->approveAsCoo($pr, $this->coo))->toThrow(RuntimeException::class);
});
