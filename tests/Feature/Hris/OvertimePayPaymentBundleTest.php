<?php

use App\Models\Hris\OvertimePayment;
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
 * Phase 7 — Overtime pay module: frozen-snapshot bundling (no recalculation), total match, drift
 * detection, paid cascade, and release on unpaid reversal.
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

it('bundles an eligible overtime batch with a matching item total and no recalculation', function () {
    $batch = F::overtime($this->employee, '75000.00');
    $bundle = $this->bundles->create($this->creator);

    $item = $this->batchBundles->addSource($bundle, $batch, $this->creator);

    expect($item->source_type)->toBe(PaymentBundleItem::SOURCE_OVERTIME_PAY_BATCH);
    expect(bccomp((string) $item->amount, '75000.00', 2))->toBe(0);
    // bundling freezes a snapshot only — the live batch + line amounts are untouched.
    expect(bccomp((string) $batch->fresh()->total_amount, '75000.00', 2))->toBe(0);
    expect(bccomp((string) $batch->payments()->first()->total_amount, '75000.00', 2))->toBe(0);
});

it('refuses to bundle an ineligible overtime batch', function () {
    $batch = F::overtime($this->employee, '75000.00');
    $batch->update(['payment_status' => PaymentBundleItem::PAYMENT_STATUS_PAID]);

    expect(fn () => $this->batchBundles->addSource($this->bundles->create($this->creator), $batch->fresh(), $this->creator))
        ->toThrow(RuntimeException::class, 'not payment eligible');
});

it('detects drift and blocks payment recording when the live overtime source changes', function () {
    $batch = F::overtime($this->employee, '75000.00');
    $bundle = $this->bundles->create($this->creator);
    $item = $this->batchBundles->addSource($bundle, $batch, $this->creator);

    $bundle = F::authorize($this->bundles, $this->signatures, $bundle, $this->financeA, $this->financeB, $this->exec, $this->creator);

    // Mutate the live source after authorization (the recorded batch total drifts from the frozen snapshot).
    $batch->update(['total_amount' => '999999.00']);

    expect($this->payments->detectDrift($item->fresh()))->toBeTrue();
    expect(fn () => $this->payments->beginPaymentRecording($bundle->fresh(), $this->creator))
        ->toThrow(RuntimeException::class, 'Batch source drift detected');
});

it('marks the overtime batch and lines paid when the bundle is marked paid', function () {
    $batch = F::overtime($this->employee, '75000.00');
    $bundle = $this->bundles->create($this->creator);
    $this->batchBundles->addSource($bundle, $batch, $this->creator);

    $bundle = F::authorize($this->bundles, $this->signatures, $bundle, $this->financeA, $this->financeB, $this->exec, $this->creator);
    $this->payments->markPaid($bundle->fresh(), $this->payer, UploadedFile::fake()->create('p.pdf', 20, 'application/pdf'), 'TRX-OT');

    expect($batch->fresh()->payment_status)->toBe(PaymentBundleItem::PAYMENT_STATUS_PAID);
    expect($batch->payments()->first()->status)->toBe(OvertimePayment::STATUS_PAID);
});

it('releases an unpaid overtime batch when the bundle is voided', function () {
    $batch = F::overtime($this->employee, '75000.00');
    $bundle = $this->bundles->create($this->creator);
    $this->batchBundles->addSource($bundle, $batch, $this->creator);
    $bundle = F::authorize($this->bundles, $this->signatures, $bundle, $this->financeA, $this->financeB, $this->exec, $this->creator);

    $this->bundles->void($bundle->fresh(), $this->creator, 'reversed before payment');

    expect($batch->fresh()->payment_status)->toBe(PaymentBundleItem::PAYMENT_STATUS_RELEASED);
});
