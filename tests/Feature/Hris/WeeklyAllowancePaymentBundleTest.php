<?php

use App\Models\Hris\PaymentBundleItem;
use App\Models\Hris\WeeklyAllowancePayment;
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
 * Phase 2 — Weekly allowance concrete pilot: bundling, eligibility, duplicate guard, total match,
 * paid cascade, and release on unpaid cancellation.
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

it('bundles an eligible weekly allowance batch with a matching item total', function () {
    $batch = F::weeklyAllowance($this->employee, '125000.00');
    $bundle = $this->bundles->create($this->creator);

    $item = $this->batchBundles->addSource($bundle, $batch, $this->creator);

    expect($item->source_type)->toBe(PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH);
    expect($item->status)->toBe(PaymentBundleItem::PAYMENT_STATUS_BUNDLED);
    expect(bccomp((string) $item->amount, '125000.00', 2))->toBe(0);
    expect(bccomp((string) $bundle->fresh()->total_amount, '125000.00', 2))->toBe(0);
    expect($batch->fresh()->payment_status)->toBe(PaymentBundleItem::PAYMENT_STATUS_BUNDLED);
});

it('refuses to bundle an ineligible weekly allowance batch', function () {
    $batch = F::weeklyAllowance($this->employee, '125000.00');
    $batch->update(['payment_status' => PaymentBundleItem::PAYMENT_STATUS_PAID, 'status' => 'paid']);
    $bundle = $this->bundles->create($this->creator);

    expect(fn () => $this->batchBundles->addSource($bundle, $batch->fresh(), $this->creator))
        ->toThrow(RuntimeException::class, 'not payment eligible');
});

it('blocks the same weekly allowance batch from entering two active bundles', function () {
    $batch = F::weeklyAllowance($this->employee, '125000.00');
    $this->batchBundles->addSource($this->bundles->create($this->creator), $batch, $this->creator);

    expect(fn () => $this->batchBundles->addSource($this->bundles->create($this->creator), $batch->fresh(), $this->creator))
        ->toThrow(RuntimeException::class, 'already in an active bundle');
});

it('marks the batch and its payable lines paid when the bundle is marked paid', function () {
    $batch = F::weeklyAllowance($this->employee, '125000.00');
    $bundle = $this->bundles->create($this->creator);
    $this->batchBundles->addSource($bundle, $batch, $this->creator);

    $bundle = F::authorize($this->bundles, $this->signatures, $bundle, $this->financeA, $this->financeB, $this->exec, $this->creator);
    $this->payments->markPaid($bundle->fresh(), $this->payer, UploadedFile::fake()->create('p.pdf', 20, 'application/pdf'), 'TRX-WA');

    expect($batch->fresh()->payment_status)->toBe(PaymentBundleItem::PAYMENT_STATUS_PAID);
    expect($batch->payments()->first()->status)->toBe(WeeklyAllowancePayment::STATUS_PAID);
});

it('releases an unpaid weekly allowance batch when the bundle is cancelled', function () {
    $batch = F::weeklyAllowance($this->employee, '125000.00');
    $bundle = $this->bundles->create($this->creator);
    $this->batchBundles->addSource($bundle, $batch, $this->creator);

    $this->bundles->cancel($bundle->fresh(), $this->creator);

    expect($batch->fresh()->payment_status)->toBe(PaymentBundleItem::PAYMENT_STATUS_RELEASED);
    expect($batch->payments()->first()->status)->toBe(WeeklyAllowancePayment::STATUS_APPROVED);
});
