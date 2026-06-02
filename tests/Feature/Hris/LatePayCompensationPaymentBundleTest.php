<?php

use App\Models\Hris\LatePayCompensationItem;
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
 * Phase 8 — Late-pay compensation module: frozen-snapshot bundling, total match, duplicate guard,
 * drift detection, paid cascade, and release on unpaid reversal.
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

it('bundles an eligible late-pay compensation batch with a matching item total', function () {
    $batch = F::latePay($this->employee, '50000.00');
    $bundle = $this->bundles->create($this->creator);

    $item = $this->batchBundles->addSource($bundle, $batch, $this->creator);

    expect($item->source_type)->toBe(PaymentBundleItem::SOURCE_LATE_PAY_COMPENSATION_BATCH);
    expect(bccomp((string) $item->amount, '50000.00', 2))->toBe(0);
    expect(bccomp((string) $bundle->fresh()->total_amount, '50000.00', 2))->toBe(0);
    expect($batch->fresh()->payment_status)->toBe(PaymentBundleItem::PAYMENT_STATUS_BUNDLED);
});

it('blocks the same late-pay batch from entering two active bundles', function () {
    $batch = F::latePay($this->employee, '50000.00');
    $this->batchBundles->addSource($this->bundles->create($this->creator), $batch, $this->creator);

    expect(fn () => $this->batchBundles->addSource($this->bundles->create($this->creator), $batch->fresh(), $this->creator))
        ->toThrow(RuntimeException::class, 'already in an active bundle');
});

it('detects drift and blocks payment recording when the live late-pay source changes', function () {
    $batch = F::latePay($this->employee, '50000.00');
    $bundle = $this->bundles->create($this->creator);
    $item = $this->batchBundles->addSource($bundle, $batch, $this->creator);

    $bundle = F::authorize($this->bundles, $this->signatures, $bundle, $this->financeA, $this->financeB, $this->exec, $this->creator);

    // Mutate the live source after authorization (the recorded batch total drifts from the frozen snapshot).
    $batch->update(['total_amount' => '888888.00']);

    expect($this->payments->detectDrift($item->fresh()))->toBeTrue();
    expect(fn () => $this->payments->beginPaymentRecording($bundle->fresh(), $this->creator))
        ->toThrow(RuntimeException::class, 'Batch source drift detected');
});

it('marks the late-pay batch and lines paid when the bundle is marked paid', function () {
    $batch = F::latePay($this->employee, '50000.00');
    $bundle = $this->bundles->create($this->creator);
    $this->batchBundles->addSource($bundle, $batch, $this->creator);

    $bundle = F::authorize($this->bundles, $this->signatures, $bundle, $this->financeA, $this->financeB, $this->exec, $this->creator);
    $this->payments->markPaid($bundle->fresh(), $this->payer, UploadedFile::fake()->create('p.pdf', 20, 'application/pdf'), 'TRX-LPC');

    expect($batch->fresh()->payment_status)->toBe(PaymentBundleItem::PAYMENT_STATUS_PAID);
    expect($batch->items()->first()->status)->toBe(LatePayCompensationItem::STATUS_PAID);
});

it('releases an unpaid late-pay batch when the bundle is cancelled', function () {
    $batch = F::latePay($this->employee, '50000.00');
    $bundle = $this->bundles->create($this->creator);
    $this->batchBundles->addSource($bundle, $batch, $this->creator);

    $this->bundles->cancel($bundle->fresh(), $this->creator);

    expect($batch->fresh()->payment_status)->toBe(PaymentBundleItem::PAYMENT_STATUS_RELEASED);
});
