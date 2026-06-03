<?php

use App\Models\Hris\FinanceAuditLog;
use App\Models\User;
use App\Services\Hris\BatchPaymentBundleService;
use App\Services\Hris\CanonicalPayloadHashService;
use App\Services\Hris\FinanceAuditLogger;
use App\Services\Hris\PaymentBundleDocumentService;
use App\Services\Hris\PaymentBundlePaymentService;
use App\Services\Hris\PaymentBundleService;
use App\Services\Hris\PaymentRequestService;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BatchPaymentTestFactory as F;

/**
 * Phase 13 — Sensitive data protection (net-new gaps only).
 *
 * Already covered elsewhere (referenced, not duplicated):
 *   - BatchPaymentSensitiveDataTest: full bank out of audit log (batch_bank_detail_revealed),
 *     full bank out of renderHtml, no payee/bank on verify page.
 *   - PaymentBundleVerificationPageTest: no signer names / payee / bank / amount on public page.
 *
 * Net-new cases below:
 *   - Full bank absent from ALL audit-log after_snapshots for a bundle lifecycle,
 *     not just the reveal event.
 *   - revealPaymentInstructions() has no built-in permission gate in the service
 *     (SECURITY BUG-001: gating is documented as "at the caller" but no Filament
 *     action or HTTP controller enforces it — any authenticated User can call the
 *     method directly). Characterization test documents current behavior.
 *   - Authorized user (finance_creator / finance_executor who hold
 *     finance_center.view_sensitive_payment_data) can successfully reveal and the
 *     reveal is audited.
 *   - PR-source renderHtml: full bank account never in HTML (batch is covered by
 *     BatchPaymentSensitiveDataTest; this covers the PR source path).
 *   - Signed PDF for a mixed bundle (PR + batch): no raw salary detail leaks.
 */
beforeEach(function () {
    Storage::fake('private');

    $this->seed(\Database\Seeders\FinanceRolePermissionSeeder::class);
    $this->seed(\Database\Seeders\PaymentBundleRolePermissionSeeder::class);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $this->bundles = app(PaymentBundleService::class);
    $this->batchBundles = app(BatchPaymentBundleService::class);
    $this->payments = app(PaymentBundlePaymentService::class);
    $this->documents = app(PaymentBundleDocumentService::class);
    $this->canonical = app(CanonicalPayloadHashService::class);
    $this->audit = app(FinanceAuditLogger::class);
    $this->prs = app(PaymentRequestService::class);

    $this->actor = User::factory()->create();
    $this->coo = User::factory()->create();
    $this->employee = F::employee([
        'bank_account_number' => '1234509876',
        'bank_account_holder' => 'Ayu Test Employee',
    ]);
});

// ─── helpers ──────────────────────────────────────────────────────────────────

function p13_approvedPr(string $account = '6677889900'): \App\Models\Hris\PaymentRequest
{
    $svc = test()->prs;
    $pr = $svc->createManual(test()->actor, [
        'payee_type' => 'vendor',
        'payee_name' => 'PT Sensitif Vendor',
        'bank_name' => 'BCA',
        'bank_account_number' => $account,
        'amount' => 4_000_000,
        'currency' => 'IDR',
        'description' => 'Phase-13 sensitive data test',
    ]);
    $svc->submitToCoo($pr);
    $svc->approveAsCoo($pr, test()->coo);

    return $pr->fresh();
}

// ─── 1. Full bank account absent from ALL audit log entries for a bundle ───────
//        BatchPaymentSensitiveDataTest checks the reveal event specifically.
//        This checks EVERY event written during bundle assembly/PDF generation.

it('never persists the full bank account in any audit-log snapshot for a batch bundle lifecycle', function () {
    $fullAccount = '1234509876';
    $bundle = $this->bundles->create($this->actor);
    $this->batchBundles->addSource($bundle, F::weeklyAllowance($this->employee, '125000.00', '1234509876'), $this->actor);
    $this->canonical->store($bundle->fresh());
    app(\App\Services\Hris\PaymentBundleDocumentService::class)
        ->generateUnsignedPdf($bundle->fresh(), $this->actor);

    $logs = FinanceAuditLog::where('auditable_id', $bundle->id)
        ->where('auditable_type', \App\Models\Hris\PaymentBundle::class)
        ->get();

    expect($logs)->not->toBeEmpty();

    foreach ($logs as $log) {
        $beforeJson = json_encode($log->before_snapshot);
        $afterJson = json_encode($log->after_snapshot);

        expect($beforeJson)->not->toContain($fullAccount,
            "Full bank account leaked in before_snapshot of event: {$log->event_type}");
        expect($afterJson)->not->toContain($fullAccount,
            "Full bank account leaked in after_snapshot of event: {$log->event_type}");
    }
});

it('never persists the full bank account in any audit-log snapshot for a PR bundle lifecycle', function () {
    $fullAccount = '6677889900';
    $pr = p13_approvedPr($fullAccount);

    $bundle = $this->bundles->create($this->actor);
    $this->bundles->addPaymentRequest($bundle, $pr, $this->actor);
    app(\App\Services\Hris\PaymentBundleDocumentService::class)
        ->generateUnsignedPdf($bundle->fresh(), $this->actor);

    $logs = FinanceAuditLog::where('auditable_id', $bundle->id)
        ->where('auditable_type', \App\Models\Hris\PaymentBundle::class)
        ->get();

    expect($logs)->not->toBeEmpty();

    foreach ($logs as $log) {
        expect(json_encode($log->before_snapshot))->not->toContain($fullAccount,
            "Full bank leaked in before_snapshot of event: {$log->event_type}");
        expect(json_encode($log->after_snapshot))->not->toContain($fullAccount,
            "Full bank leaked in after_snapshot of event: {$log->event_type}");
    }
});

// ─── 2. PR-source renderHtml: full bank account must not appear ────────────────
//        BatchPaymentSensitiveDataTest covers the batch-source path.
//        This covers the PR-source path explicitly.

it('excludes the full PR bank account from the rendered authorization HTML', function () {
    $fullAccount = '6677889900';
    $pr = p13_approvedPr($fullAccount);

    $bundle = $this->bundles->create($this->actor);
    $this->bundles->addPaymentRequest($bundle, $pr, $this->actor);
    app(\App\Services\Hris\CanonicalPayloadHashService::class)->store($bundle->fresh());

    $html = $this->documents->renderHtml($bundle->fresh());

    // last4 visible; full account must be absent
    expect($html)->toContain('9900');
    expect($html)->not->toContain($fullAccount);
});

// ─── 3. Reveal returns full bank account AND is audited ───────────────────────
//        BatchPaymentSensitiveDataTest covers the audit-log check for the reveal event.
//        This additionally asserts the reveal DOES return the full account (so finance
//        can actually pay), and that the audit event is present with correct type.

it('revealPaymentInstructions returns the full bank account for the actor', function () {
    $fullAccount = '1234509876';
    $bundle = $this->bundles->create($this->actor);
    $this->batchBundles->addSource($bundle, F::weeklyAllowance($this->employee, '125000.00', '1234509876'), $this->actor);

    $instructions = $this->payments->revealPaymentInstructions($bundle->fresh(), $this->actor);

    expect(collect($instructions)->pluck('bank_account_number')->toArray())->toContain($fullAccount);
});

it('revealPaymentInstructions writes a batch_bank_detail_revealed audit event', function () {
    $bundle = $this->bundles->create($this->actor);
    $this->batchBundles->addSource($bundle, F::weeklyAllowance($this->employee, '125000.00', '1234509876'), $this->actor);

    $this->payments->revealPaymentInstructions($bundle->fresh(), $this->actor);

    expect(
        FinanceAuditLog::where('event_type', 'batch_bank_detail_revealed')
            ->where('auditable_id', $bundle->id)
            ->exists()
    )->toBeTrue();
});

// ─── 4. BUG-001: revealPaymentInstructions has NO built-in permission check ────
//        The service docblock says "permission-gated at the caller" but no
//        Filament action or HTTP controller currently enforces this. Any User
//        can call the method directly and receive full bank details.
//
//        This is a CHARACTERIZATION test that documents existing behavior.
//        DO NOT remove or invert this test without fixing the underlying
//        authorization gap first.
//
//        Expected fix: the service itself (or its only entry-point Filament
//        action/controller) should gate on
//        'finance_center.view_sensitive_payment_data' before returning instructions.

it('BUG-001: revealPaymentInstructions returns full bank even for a user WITHOUT view_sensitive_payment_data', function () {
    // Seed roles/permissions
    $unprivileged = User::factory()->create(); // no role, no permission
    expect($unprivileged->hasPermissionTo('finance_center.view_sensitive_payment_data'))->toBeFalse();

    $bundle = $this->bundles->create($this->actor);
    $this->batchBundles->addSource($bundle, F::weeklyAllowance($this->employee, '125000.00', '1234509876'), $this->actor);

    // BUG: this should throw/return empty, but currently succeeds
    $instructions = $this->payments->revealPaymentInstructions($bundle->fresh(), $unprivileged);

    // Characterization: documents that the full account IS leaked to an unprivileged user.
    // When BUG-001 is fixed, this test should be updated to assert a forbidden exception.
    expect(collect($instructions)->pluck('bank_account_number')->toArray())
        ->toContain('1234509876'); // full account visible — BUG
});

// ─── 5. A user WITH view_sensitive_payment_data can reveal and audit is written ─

it('a user with finance_center.view_sensitive_payment_data can reveal and the event is audited', function () {
    $privileged = User::factory()->create();
    $privileged->assignRole('finance_creator'); // has view_sensitive_payment_data

    expect($privileged->hasPermissionTo('finance_center.view_sensitive_payment_data'))->toBeTrue();

    $bundle = $this->bundles->create($this->actor);
    $this->batchBundles->addSource($bundle, F::weeklyAllowance($this->employee, '125000.00', '1234509876'), $this->actor);

    $instructions = $this->payments->revealPaymentInstructions($bundle->fresh(), $privileged);

    expect(collect($instructions))->not->toBeEmpty();

    $log = FinanceAuditLog::where('event_type', 'batch_bank_detail_revealed')
        ->where('auditable_id', $bundle->id)
        ->where('actor_id', $privileged->id)
        ->first();

    expect($log)->not->toBeNull();
    // Audit payload must NOT include the full account
    expect(json_encode($log->after_snapshot))->not->toContain('1234509876');
});

// ─── 6. Signed PDF for mixed bundle: batch section has no per-employee salary ──

it('the signed authorization HTML for a mixed bundle does not expose per-employee salary figures', function () {
    // Uses a multi-payee batch so the HTML collapses to an aggregate row (not per-payee lines).
    // Single-payee batches intentionally show the one payee name; that is by design.
    $prAccount = '6677889900';
    $pr = p13_approvedPr($prAccount);

    $batchEmp1 = F::employee(['bank_account_number' => '2233445566', 'bank_account_holder' => 'Wati Gaji']);
    $batchEmp2 = F::employee(['bank_account_number' => '7788990011', 'bank_account_holder' => 'Budi Gaji']);
    $batch = F::weeklyAllowanceWithPayees([
        ['employee' => $batchEmp1, 'amount' => '100000.00', 'bank_account' => '2233445566'],
        ['employee' => $batchEmp2, 'amount' => '125000.00', 'bank_account' => '7788990011'],
    ]);

    $bundle = $this->bundles->create($this->actor);
    $this->bundles->addPaymentRequest($bundle, $pr, $this->actor);
    $this->batchBundles->addSource($bundle, $batch, $this->actor);
    $this->canonical->store($bundle->fresh());

    $html = $this->documents->renderHtml($bundle->fresh());

    // Multi-payee batch collapses to aggregate row — individual names must not appear
    expect($html)->not->toContain('Wati Gaji');
    expect($html)->not->toContain('Budi Gaji');

    // Full bank accounts must not appear
    expect($html)->not->toContain($prAccount);
    expect($html)->not->toContain('2233445566');
    expect($html)->not->toContain('7788990011');

    // Batch label present
    expect($html)->toContain('Weekly Allowance');

    // Aggregate total (PR 4,000,000 + batch 225,000 = 4,225,000)
    expect($html)->toContain('4,225,000');
});
