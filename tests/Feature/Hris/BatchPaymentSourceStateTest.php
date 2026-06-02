<?php

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
 * Phase 0.5 — Canonical source payment state machine. One lifecycle every adapter shares:
 * eligible → bundled → authorized → recording → paid, with release back out only while unpaid.
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

function stateProof(): UploadedFile
{
    return UploadedFile::fake()->create('proof.pdf', 20, 'application/pdf');
}

it('transitions a source eligible → bundled → authorized → recording → paid', function () {
    $batch = F::weeklyAllowance($this->employee, '125000.00');
    expect($batch->payment_status)->toBe(PaymentBundleItem::PAYMENT_STATUS_ELIGIBLE);

    $bundle = $this->bundles->create($this->creator);
    $this->batchBundles->addSource($bundle, $batch, $this->creator);
    expect($batch->fresh()->payment_status)->toBe(PaymentBundleItem::PAYMENT_STATUS_BUNDLED);

    $bundle = F::authorize($this->bundles, $this->signatures, $bundle, $this->financeA, $this->financeB, $this->exec, $this->creator);
    expect($batch->fresh()->payment_status)->toBe(PaymentBundleItem::PAYMENT_STATUS_AUTHORIZED);

    $this->payments->beginPaymentRecording($bundle->fresh(), $this->creator);
    expect($batch->fresh()->payment_status)->toBe(PaymentBundleItem::PAYMENT_STATUS_RECORDING);

    $this->payments->markPaid($bundle->fresh(), $this->payer, stateProof(), 'TRX-STATE');
    expect($batch->fresh()->payment_status)->toBe(PaymentBundleItem::PAYMENT_STATUS_PAID);
});

it('releases a bundled source when the draft bundle is cancelled', function () {
    $batch = F::weeklyAllowance($this->employee, '125000.00');
    $bundle = $this->bundles->create($this->creator);
    $this->batchBundles->addSource($bundle, $batch, $this->creator);

    $this->bundles->cancel($bundle->fresh(), $this->creator);

    expect($batch->fresh()->payment_status)->toBe(PaymentBundleItem::PAYMENT_STATUS_RELEASED);
});

it('does not release a paid source: a paid bundle cannot be voided', function () {
    $batch = F::weeklyAllowance($this->employee, '125000.00');
    $bundle = $this->bundles->create($this->creator);
    $this->batchBundles->addSource($bundle, $batch, $this->creator);
    $bundle = F::authorize($this->bundles, $this->signatures, $bundle, $this->financeA, $this->financeB, $this->exec, $this->creator);
    $this->payments->markPaid($bundle->fresh(), $this->payer, stateProof(), 'TRX-PAID');

    expect($batch->fresh()->payment_status)->toBe(PaymentBundleItem::PAYMENT_STATUS_PAID);

    expect(fn () => $this->bundles->void($bundle->fresh(), $this->creator, 'too late'))
        ->toThrow(RuntimeException::class, 'cannot be voided');

    expect($batch->fresh()->payment_status)->toBe(PaymentBundleItem::PAYMENT_STATUS_PAID);
});

it('blocks an illegal transition: adding a source to a non-draft bundle', function () {
    $batch = F::weeklyAllowance($this->employee, '125000.00');
    $bundle = $this->bundles->create($this->creator);
    $this->batchBundles->addSource($bundle, $batch, $this->creator);
    $bundle = F::authorize($this->bundles, $this->signatures, $bundle, $this->financeA, $this->financeB, $this->exec, $this->creator);

    expect(fn () => $this->batchBundles->addSource($bundle->fresh(), F::overtime($this->employee, '75000.00'), $this->creator))
        ->toThrow(RuntimeException::class, 'Only a draft bundle can receive items');
});

it('protects an active source with a DB-level uniqueness guard against a second active bundle', function () {
    $batch = F::weeklyAllowance($this->employee, '125000.00');
    $bundleA = $this->bundles->create($this->creator);
    $bundleB = $this->bundles->create($this->creator);

    $this->batchBundles->addSource($bundleA, $batch, $this->creator);

    expect(fn () => $this->batchBundles->addSource($bundleB, $batch->fresh(), $this->creator))
        ->toThrow(RuntimeException::class, 'already in an active bundle');
});
