<?php

use App\Enums\PaymentBundleSignatureSlot;
use App\Models\Hris\PaymentBundleItem;
use App\Models\User;
use App\Services\Hris\BatchPaymentBundleService;
use App\Services\Hris\PaymentBundlePaymentService;
use App\Services\Hris\PaymentBundleService;
use App\Services\Hris\PaymentBundleSignatureService;
use Database\Seeders\FinanceRolePermissionSeeder;
use Database\Seeders\PaymentBundleRolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\BatchPaymentTestFactory as F;

/**
 * Phase 14 — Reverse transitions: unpaid bundles release their batch sources on cancel/void/reject;
 * a paid bundle never does; stored PDFs are retained for audit.
 */
beforeEach(function () {
    Storage::fake('private');
    $this->seed(FinanceRolePermissionSeeder::class);
    $this->seed(PaymentBundleRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->bundles = app(PaymentBundleService::class);
    $this->batchBundles = app(BatchPaymentBundleService::class);
    $this->signatures = app(PaymentBundleSignatureService::class);
    $this->payments = app(PaymentBundlePaymentService::class);

    $this->creator = User::factory()->create();
    $this->financeA = tap(User::factory()->create())->assignRole('finance_signer');
    $this->financeB = tap(User::factory()->create())->assignRole('finance_signer');
    $this->exec = tap(User::factory()->create())->assignRole('executive_signer');
    $this->payer = User::factory()->create();
    $this->employee = F::employee();
});

it('releases the batch source when a draft bundle is cancelled', function () {
    $batch = F::weeklyAllowance($this->employee, '125000.00');
    $bundle = $this->bundles->create($this->creator);
    $this->batchBundles->addSource($bundle, $batch, $this->creator);

    $this->bundles->cancel($bundle->fresh(), $this->creator);

    expect($batch->fresh()->payment_status)->toBe(PaymentBundleItem::PAYMENT_STATUS_RELEASED);
});

it('releases the batch source when an authorized unpaid bundle is voided, keeping stored PDFs', function () {
    $batch = F::weeklyAllowance($this->employee, '125000.00');
    $bundle = $this->bundles->create($this->creator);
    $this->batchBundles->addSource($bundle, $batch, $this->creator);
    $bundle = F::authorize($this->bundles, $this->signatures, $bundle, $this->financeA, $this->financeB, $this->exec, $this->creator);

    expect($bundle->unsigned_pdf_attachment_id)->not->toBeNull();
    expect($bundle->signed_pdf_attachment_id)->not->toBeNull();

    $voided = $this->bundles->void($bundle->fresh(), $this->creator, 'reversed before payment');

    expect($batch->fresh()->payment_status)->toBe(PaymentBundleItem::PAYMENT_STATUS_RELEASED);
    // PDFs remain attached for the audit trail.
    expect($voided->unsigned_pdf_attachment_id)->not->toBeNull();
    expect($voided->signed_pdf_attachment_id)->not->toBeNull();
});

it('releases the batch source when a signer rejects', function () {
    $batch = F::weeklyAllowance($this->employee, '125000.00');
    $bundle = $this->bundles->create($this->creator);
    $this->batchBundles->addSource($bundle, $batch, $this->creator);
    $bundle = $this->bundles->submitForSignature($bundle->fresh(), [
        PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value => $this->financeA->id,
        PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value => $this->financeB->id,
        PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value => $this->exec->id,
    ], $this->creator);

    $sig = $bundle->fresh()->signatures()->where('signature_slot', PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value)->firstOrFail();
    $this->bundles->rejectSignature($sig, $this->financeA, 'declined');

    expect($batch->fresh()->payment_status)->toBe(PaymentBundleItem::PAYMENT_STATUS_RELEASED);
});

it('never releases a source from a paid bundle', function () {
    $batch = F::weeklyAllowance($this->employee, '125000.00');
    $bundle = $this->bundles->create($this->creator);
    $this->batchBundles->addSource($bundle, $batch, $this->creator);
    $bundle = F::authorize($this->bundles, $this->signatures, $bundle, $this->financeA, $this->financeB, $this->exec, $this->creator);
    $this->payments->markPaid($bundle->fresh(), $this->payer, UploadedFile::fake()->create('p.pdf', 20, 'application/pdf'), 'TRX-REV');

    expect($batch->fresh()->payment_status)->toBe(PaymentBundleItem::PAYMENT_STATUS_PAID);

    // A paid bundle cannot be voided, so the source can never be silently released.
    expect(fn () => $this->bundles->void($bundle->fresh(), $this->creator, 'too late'))
        ->toThrow(RuntimeException::class, 'cannot be voided');
    expect($batch->fresh()->payment_status)->toBe(PaymentBundleItem::PAYMENT_STATUS_PAID);
});
