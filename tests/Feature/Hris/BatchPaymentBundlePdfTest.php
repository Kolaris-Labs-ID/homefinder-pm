<?php

use App\Models\User;
use App\Services\Hris\BatchPaymentBundleService;
use App\Services\Hris\CanonicalPayloadHashService;
use App\Services\Hris\PaymentBundleDocumentService;
use App\Services\Hris\PaymentBundleService;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BatchPaymentTestFactory as F;

/**
 * Phase 13 — PDF template safety. The authorization document renders batch sections as a safe
 * summary: canonical hash, batch label, masked last4, aggregate total — never the full bank account
 * or per-employee salary lines.
 */
beforeEach(function () {
    Storage::fake('private');
    $this->bundles = app(PaymentBundleService::class);
    $this->batchBundles = app(BatchPaymentBundleService::class);
    $this->documents = app(PaymentBundleDocumentService::class);
    $this->canonical = app(CanonicalPayloadHashService::class);
    $this->creator = User::factory()->create();
});

function pdfRenderBatchBundle(): string
{
    $e1 = F::employee(['bank_account_holder' => 'Ayu One', 'bank_account_number' => '9876543210']);
    $e2 = F::employee(['bank_account_holder' => 'Budi Two', 'bank_account_number' => '1112223333']);

    $batch = F::weeklyAllowanceWithPayees([
        ['employee' => $e1, 'amount' => '125000.00', 'bank_account' => '9876543210'],
        ['employee' => $e2, 'amount' => '75000.00', 'bank_account' => '1112223333'],
    ]);

    $bundle = test()->bundles->create(test()->creator);
    test()->batchBundles->addSource($bundle, $batch, test()->creator);
    test()->canonical->store($bundle->fresh());

    return test()->documents->renderHtml($bundle->fresh());
}

it('renders the canonical payload hash and the batch section label', function () {
    $bundle = $this->bundles->create($this->creator);
    $this->batchBundles->addSource($bundle, F::weeklyAllowance(F::employee(), '125000.00'), $this->creator);
    $this->canonical->store($bundle->fresh());

    $html = $this->documents->renderHtml($bundle->fresh());

    expect($html)->toContain($bundle->fresh()->canonical_payload_hash);
    expect($html)->toContain('Weekly Allowance');
});

it('shows the masked last4 but never the full bank account', function () {
    $html = pdfRenderBatchBundle();

    expect($html)->toContain('3210');               // masked last4 of the first payee
    expect($html)->not->toContain('9876543210');     // full account #1
    expect($html)->not->toContain('1112223333');     // full account #2
});

it('renders a safe aggregate summary, not per-employee salary lines', function () {
    $html = pdfRenderBatchBundle();

    // Multi-payee batch collapses to one aggregate row — the individual payees are not itemized.
    expect($html)->not->toContain('Budi Two');
    expect($html)->toContain('200,000.00'); // aggregate total
});
