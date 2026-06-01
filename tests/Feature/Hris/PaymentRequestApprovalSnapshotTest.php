<?php

use App\Models\Hris\PaymentRequest;
use App\Models\User;
use App\Services\Hris\PaymentRequestService;

/**
 * Phase 6 — immutable COO business-approval snapshot (EXPENSE_MODULE.MD).
 */
beforeEach(function () {
    $this->service = app(PaymentRequestService::class);
    $this->creator = User::factory()->create();
    $this->coo = User::factory()->create();
});

function approvedPr(): PaymentRequest
{
    $pr = test()->service->createManual(test()->creator, [
        'payee_type' => 'vendor', 'payee_name' => 'PT Vendor', 'bank_name' => 'BCA',
        'bank_account_number' => '1234567890', 'amount' => 5000000, 'currency' => 'IDR',
        'description' => 'Supplies',
    ]);
    test()->service->submitToCoo($pr);
    test()->service->approveAsCoo($pr, test()->coo);

    return $pr->fresh();
}

it('creates a snapshot json + hash on COO approval', function () {
    $pr = approvedPr();

    expect($pr->approval_snapshot_json)->not->toBeNull();
    expect($pr->approval_snapshot_hash)->not->toBeNull();
    expect($pr->approval_snapshot_json['amount'])->toBe('5000000.00');
    expect($pr->approval_snapshot_json['bank_account_last4'])->toBe('7890');
    expect($pr->approval_snapshot_json['payee_name'])->toBe('PT Vendor');
});

it('produces a hash that is stable for unchanged approved data', function () {
    $pr = approvedPr();

    $recomputed = $this->service->hashSnapshot($pr->approval_snapshot_json);

    expect($recomputed)->toBe($pr->approval_snapshot_hash);
});

it('voids approval and requires re-approval when the approved amount changes', function () {
    $pr = approvedPr();
    $originalHash = $pr->approval_snapshot_hash;

    $this->service->updateApprovedDetails($pr, ['amount' => 9000000]);

    $fresh = $pr->fresh();
    expect($fresh->status)->toBe(PaymentRequest::STATUS_DRAFT);
    expect($fresh->approval_snapshot_hash)->toBeNull();
    expect($fresh->coo_approved_by)->toBeNull();

    // Re-approval produces a different snapshot hash (business data changed).
    $this->service->submitToCoo($fresh);
    $this->service->approveAsCoo($fresh, $this->coo);
    expect($fresh->fresh()->approval_snapshot_hash)->not->toBe($originalHash);
});

it('blocks bank-account redirection after approval (forces re-approval)', function () {
    $pr = approvedPr();

    $this->service->updateApprovedDetails($pr, ['bank_account_number' => '5555555555']);

    $fresh = $pr->fresh();
    expect($fresh->status)->toBe(PaymentRequest::STATUS_DRAFT);
    expect($fresh->bank_account_last4)->toBe('5555');
    expect($fresh->approval_snapshot_hash)->toBeNull();
});
