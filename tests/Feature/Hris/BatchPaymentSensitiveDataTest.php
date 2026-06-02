<?php

use App\Models\Hris\FinanceAuditLog;
use App\Models\User;
use App\Services\Hris\BatchPaymentBundleService;
use App\Services\Hris\CanonicalPayloadHashService;
use App\Services\Hris\PaymentBundleDocumentService;
use App\Services\Hris\PaymentBundlePaymentService;
use App\Services\Hris\PaymentBundleService;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BatchPaymentTestFactory as F;

/**
 * Phase 11 — Sensitive data handling. Full bank detail is reachable only through the audited reveal
 * path; the signed PDF, the public verification page, and the audit log itself expose last4 only.
 */
beforeEach(function () {
    Storage::fake('private');

    $this->bundles = app(PaymentBundleService::class);
    $this->batchBundles = app(BatchPaymentBundleService::class);
    $this->payments = app(PaymentBundlePaymentService::class);
    $this->documents = app(PaymentBundleDocumentService::class);
    $this->canonical = app(CanonicalPayloadHashService::class);

    $this->actor = User::factory()->create();
    $this->employee = F::employee(['bank_account_number' => '9876543210']);
});

it('returns the full bank account only through the audited reveal path', function () {
    $bundle = $this->bundles->create($this->actor);
    $this->batchBundles->addSource($bundle, F::weeklyAllowance($this->employee, '125000.00'), $this->actor);

    $instructions = $this->payments->revealPaymentInstructions($bundle->fresh(), $this->actor);

    // The reveal hands finance the full account for manual payment.
    expect(collect($instructions)->pluck('bank_account_number'))->toContain('9876543210');

    // The reveal is audited, but the audit payload never persists the full account.
    $row = FinanceAuditLog::where('event_type', 'batch_bank_detail_revealed')
        ->where('auditable_id', $bundle->id)
        ->firstOrFail();

    expect(json_encode($row->after_snapshot))->not->toContain('9876543210');
});

it('keeps the full bank account out of the signed authorization PDF', function () {
    $bundle = $this->bundles->create($this->actor);
    $this->batchBundles->addSource($bundle, F::weeklyAllowance($this->employee, '125000.00'), $this->actor);
    $this->canonical->store($bundle->fresh());

    $html = $this->documents->renderHtml($bundle->fresh());

    expect($html)->toContain('3210');           // masked last4
    expect($html)->not->toContain('9876543210'); // full account
});

it('exposes neither full bank account nor payee name on the public verification page', function () {
    $bundle = $this->bundles->create($this->actor);
    $this->batchBundles->addSource($bundle, F::weeklyAllowance($this->employee, '125000.00'), $this->actor);
    $this->canonical->store($bundle->fresh());

    $response = $this->get('/verify/payment-document/'.$bundle->fresh()->public_verification_code);

    $response->assertOk();
    expect($response->getContent())->not->toContain('9876543210');
    expect($response->getContent())->not->toContain('Ayu Finance');
});
