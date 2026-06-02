<?php

use App\Models\Hris\FinanceAuditLog;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleItem;
use App\Models\User;
use App\Services\Hris\BatchPaymentBundleService;
use App\Services\Hris\FinanceAuditLogger;
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
 * Phase 15 — Batch payment audit events: per-source add events, marked-paid, blocked-missing-data,
 * released, drift, and an unbroken hash chain across the full batch lifecycle.
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
    $this->logger = app(FinanceAuditLogger::class);

    $this->creator = User::factory()->create();
    $this->financeA = User::factory()->create();
    $this->financeA->assignRole('finance_signer');
    $this->financeB = User::factory()->create();
    $this->financeB->assignRole('finance_signer');
    $this->exec = User::factory()->create();
    $this->exec->assignRole('executive_signer');
    $this->payer = User::factory()->create();

    $this->employee = F::employee();
});

function batchAuditBundleEvents(int $bundleId): array
{
    return FinanceAuditLog::where('auditable_type', PaymentBundle::class)
        ->where('auditable_id', $bundleId)
        ->pluck('event_type')
        ->all();
}

it('emits the per-source added events for weekly allowance, overtime, and late pay', function () {
    $bundle = $this->bundles->create($this->creator);

    $this->batchBundles->addSource($bundle, F::weeklyAllowance($this->employee), $this->creator);
    $this->batchBundles->addSource($bundle->fresh(), F::overtime($this->employee), $this->creator);
    $this->batchBundles->addSource($bundle->fresh(), F::latePay($this->employee), $this->creator);

    $events = batchAuditBundleEvents($bundle->id);

    expect($events)->toContain('batch_source_added_to_bundle');
    expect($events)->toContain('weekly_allowance_added_to_bundle');
    expect($events)->toContain('overtime_pay_added_to_bundle');
    expect($events)->toContain('late_pay_added_to_bundle');
});

it('emits batch_source_blocked_missing_data when a source is blocked for missing payable data', function () {
    $bundle = $this->bundles->create($this->creator);
    $weekly = F::weeklyAllowance($this->employee, '125000.00', bankAccount: null);

    expect(fn () => $this->batchBundles->addSource($bundle, $weekly, $this->creator))
        ->toThrow(RuntimeException::class, 'missing payable data');

    $row = FinanceAuditLog::where('event_type', 'batch_source_blocked_missing_data')
        ->where('auditable_id', $bundle->id)
        ->firstOrFail();

    expect($row->after_snapshot['source_type'])->toBe(PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH);
    expect($row->after_snapshot['source_id'])->toBe((string) $weekly->id);
    expect($row->after_snapshot)->toHaveKey('blocked_reason');
});

it('blocked-missing-data audit payload never contains the full bank account', function () {
    $bundle = $this->bundles->create($this->creator);
    // Total deliberately mismatched against the single payable line to trigger an invalid-data block.
    $weekly = F::weeklyAllowance($this->employee, '125000.00');
    $weekly->update(['total_amount' => '999999.00']);

    expect(fn () => $this->batchBundles->addSource($bundle, $weekly->fresh(), $this->creator))
        ->toThrow(RuntimeException::class);

    $row = FinanceAuditLog::where('event_type', 'batch_source_blocked_missing_data')
        ->where('auditable_id', $bundle->id)
        ->firstOrFail();

    expect(json_encode($row->after_snapshot))->not->toContain('9876543210');
});

it('emits batch_source_marked_paid for each linked source when the bundle is marked paid', function () {
    $bundle = $this->bundles->create($this->creator);
    $weekly = F::weeklyAllowance($this->employee, '125000.00');
    $overtime = F::overtime($this->employee, '75000.00');

    $this->batchBundles->addSource($bundle, $weekly, $this->creator);
    $this->batchBundles->addSource($bundle->fresh(), $overtime, $this->creator);

    $bundle = F::authorize($this->bundles, $this->signatures, $bundle, $this->financeA, $this->financeB, $this->exec, $this->creator);
    $this->payments->beginPaymentRecording($bundle->fresh(), $this->creator);

    $this->payments->markPaid($bundle->fresh(), $this->payer, UploadedFile::fake()->create('proof.pdf', 30, 'application/pdf'), 'TRX-AUDIT');

    $markedPaid = FinanceAuditLog::where('event_type', 'batch_source_marked_paid')
        ->where('auditable_id', $bundle->id)
        ->get();

    expect($markedPaid)->toHaveCount(2);

    $weeklyRow = $markedPaid->firstWhere(fn ($r) => $r->after_snapshot['source_type'] === PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH);
    expect($weeklyRow->after_snapshot['source_payment_status'])->toBe(PaymentBundleItem::PAYMENT_STATUS_PAID);
    expect($weeklyRow->after_snapshot)->toHaveKey('canonical_payload_hash');
    expect($weeklyRow->after_snapshot)->toHaveKey('payment_proof_attachment_id');
    expect($weeklyRow->before_snapshot)->toHaveKey('source_payment_status');
});

it('emits batch_source_released_from_bundle when a draft bundle is cancelled', function () {
    $bundle = $this->bundles->create($this->creator);
    $weekly = F::weeklyAllowance($this->employee, '125000.00');
    $this->batchBundles->addSource($bundle, $weekly, $this->creator);

    $this->bundles->cancel($bundle->fresh(), $this->creator);

    expect(batchAuditBundleEvents($bundle->id))->toContain('batch_source_released_from_bundle');
});

it('keeps the audit hash chain unbroken across the full batch lifecycle', function () {
    $bundle = $this->bundles->create($this->creator);
    $this->batchBundles->addSource($bundle, F::weeklyAllowance($this->employee, '125000.00'), $this->creator);

    $bundle = F::authorize($this->bundles, $this->signatures, $bundle, $this->financeA, $this->financeB, $this->exec, $this->creator);
    $this->payments->beginPaymentRecording($bundle->fresh(), $this->creator);
    $this->payments->markPaid($bundle->fresh(), $this->payer, UploadedFile::fake()->create('proof.pdf', 30, 'application/pdf'), 'TRX-CHAIN');

    $logs = FinanceAuditLog::orderBy('id')->get();
    expect($logs)->not->toBeEmpty();

    $prevHash = null;
    foreach ($logs as $log) {
        expect($this->logger->recompute($log))->toBe($log->current_hash, "Hash mismatch on log id={$log->id} event={$log->event_type}");
        expect($log->previous_hash)->toBe($prevHash, "Chain break at log id={$log->id} event={$log->event_type}");
        $prevHash = $log->current_hash;
    }
});
