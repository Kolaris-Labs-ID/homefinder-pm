<?php

use App\Enums\PaymentBundleSignatureSlot;
use App\Models\Hris\FinanceAuditLog;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleSignature;
use App\Models\Hris\PaymentRequest;
use App\Models\User;
use App\Services\Hris\FinanceAuditLogger;
use App\Services\Hris\PaymentBundlePaymentService;
use App\Services\Hris\PaymentBundleService;
use App\Services\Hris\PaymentBundleSignatureService;
use App\Services\Hris\PaymentRequestService;
use Database\Seeders\FinanceRolePermissionSeeder;
use Database\Seeders\PaymentBundleRolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 14 — Audit events: full event set, before/after status snapshots, hash chain integrity.
 */
beforeEach(function () {
    Storage::fake('private');

    $this->seed(FinanceRolePermissionSeeder::class);
    $this->seed(PaymentBundleRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->bundles = app(PaymentBundleService::class);
    $this->signatures = app(PaymentBundleSignatureService::class);
    $this->payments = app(PaymentBundlePaymentService::class);
    $this->prs = app(PaymentRequestService::class);
    $this->logger = app(FinanceAuditLogger::class);

    $this->creator = User::factory()->create();
    $this->coo = User::factory()->create();

    $this->financeA = User::factory()->create();
    $this->financeA->assignRole('finance_signer');
    $this->financeB = User::factory()->create();
    $this->financeB->assignRole('finance_signer');
    $this->exec = User::factory()->create();
    $this->exec->assignRole('executive_signer');

    $this->payer = User::factory()->create();
});

// ─── shared helpers ────────────────────────────────────────────────────────────

function auditInkPng14(): string
{
    $img = imagecreatetruecolor(100, 50);
    imagesavealpha($img, true);
    imagealphablending($img, false);
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);
    imagealphablending($img, true);
    $black = imagecolorallocate($img, 0, 0, 0);
    imageline($img, 5, 25, 95, 25, $black);
    ob_start();
    imagepng($img);
    $bytes = ob_get_clean();
    imagedestroy($img);

    return 'data:image/png;base64,'.base64_encode($bytes);
}

function auditApprovedPr14(int $amount = 5000000): PaymentRequest
{
    $svc = test()->prs;
    $pr = $svc->createManual(test()->creator, [
        'payee_type' => 'vendor',
        'payee_name' => 'PT Audit Vendor',
        'bank_name' => 'BCA',
        'bank_account_number' => '9876543210',
        'amount' => $amount,
        'currency' => 'IDR',
        'description' => 'Audit test payment',
    ]);
    $svc->submitToCoo($pr);
    $svc->approveAsCoo($pr, test()->coo);

    return $pr->fresh();
}

function auditValidAssignment14(): array
{
    return [
        PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value => test()->financeA->id,
        PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value => test()->financeB->id,
        PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value => test()->exec->id,
    ];
}

function auditSignSlot14(PaymentBundle $bundle, PaymentBundleSignatureSlot $slot): PaymentBundleSignature
{
    $sig = $bundle->signatures()->where('signature_slot', $slot->value)->firstOrFail();

    return test()->signatures->submitSignature($sig, $sig->assignedUser, auditInkPng14());
}

function auditFakePdf14(string $name = 'proof.pdf'): UploadedFile
{
    return UploadedFile::fake()->create($name, 50, 'application/pdf');
}

function auditFullLifecycleBundle14(): PaymentBundle
{
    $pr = auditApprovedPr14();
    $bundle = test()->bundles->create(test()->creator);
    test()->bundles->addPaymentRequest($bundle, $pr, test()->creator);
    $bundle = test()->bundles->submitForSignature($bundle->fresh(), auditValidAssignment14(), test()->creator);

    auditSignSlot14($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_1);
    auditSignSlot14($bundle->fresh(), PaymentBundleSignatureSlot::FINANCE_SIGNER_2);
    auditSignSlot14($bundle->fresh(), PaymentBundleSignatureSlot::EXECUTIVE_SIGNER);

    $bundle->refresh();
    test()->payments->beginPaymentRecording($bundle, test()->creator);

    return $bundle->fresh();
}

// ─── 1. Full forward lifecycle emits every event ──────────────────────────────

it('full forward lifecycle emits every expected audit event', function () {
    $bundle = auditFullLifecycleBundle14();

    $this->payments->markPaid($bundle, $this->payer, auditFakePdf14('proof-audit.pdf'));
    $bundle->refresh();

    $bundleId = $bundle->id;

    // Events audited against the bundle model.
    $bundleEvents = FinanceAuditLog::where('auditable_type', PaymentBundle::class)
        ->where('auditable_id', $bundleId)
        ->pluck('event_type')
        ->all();

    foreach ([
        'payment_bundle.created',
        'payment_bundle.item_added',
        'payment_bundle.payload_hash_generated',
        'payment_bundle.unsigned_pdf_generated',
        'payment_bundle.submitted_for_signature',
        'payment_bundle.authorized',
        'payment_bundle.signed_pdf_generated',
        'payment_bundle.payment_recording_started',
        'payment_bundle.payment_proof_uploaded',
        'payment_bundle.marked_paid',
    ] as $event) {
        expect(in_array($event, $bundleEvents, true))->toBeTrue("Missing bundle event: {$event}");
    }

    // signature_link_created and signature_submitted are audited on the PaymentBundleSignature auditable.
    $sigIds = $bundle->signatures()->pluck('id')->all();

    $sigEvents = FinanceAuditLog::where('auditable_type', PaymentBundleSignature::class)
        ->whereIn('auditable_id', $sigIds)
        ->pluck('event_type')
        ->all();

    expect($sigEvents)->toContain('payment_bundle.signature_link_created');
    expect($sigEvents)->toContain('payment_bundle.signature_submitted');
});

// ─── 2. Reverse events ────────────────────────────────────────────────────────

it('cancel emits payment_bundle.cancelled and item_released', function () {
    $pr = auditApprovedPr14();
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, $pr, $this->creator);

    $this->bundles->cancel($bundle->fresh(), $this->creator);

    expect(FinanceAuditLog::where('event_type', 'payment_bundle.cancelled')
        ->where('auditable_id', $bundle->id)->exists())->toBeTrue();

    expect(FinanceAuditLog::where('event_type', 'payment_bundle.item_released')
        ->where('auditable_id', $bundle->id)->exists())->toBeTrue();
});

it('void emits voided, signature_voided, and item_released', function () {
    $pr = auditApprovedPr14();
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, $pr, $this->creator);
    $bundle = $this->bundles->submitForSignature($bundle->fresh(), auditValidAssignment14(), $this->creator);

    auditSignSlot14($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_1);
    auditSignSlot14($bundle->fresh(), PaymentBundleSignatureSlot::FINANCE_SIGNER_2);
    auditSignSlot14($bundle->fresh(), PaymentBundleSignatureSlot::EXECUTIVE_SIGNER);
    $bundle->refresh();

    $this->bundles->void($bundle, $this->creator, 'Audit void test');

    expect(FinanceAuditLog::where('event_type', 'payment_bundle.voided')
        ->where('auditable_id', $bundle->id)->exists())->toBeTrue();

    expect(FinanceAuditLog::where('event_type', 'payment_bundle.signature_voided')
        ->where('auditable_id', $bundle->id)->exists())->toBeTrue();

    expect(FinanceAuditLog::where('event_type', 'payment_bundle.item_released')
        ->where('auditable_id', $bundle->id)->exists())->toBeTrue();
});

it('rejectSignature emits signature_rejected, signature_revoked, rejected, and item_released', function () {
    $pr = auditApprovedPr14();
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, $pr, $this->creator);
    $bundle = $this->bundles->submitForSignature($bundle->fresh(), auditValidAssignment14(), $this->creator);

    $f1sig = $bundle->signatures()
        ->where('signature_slot', PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value)
        ->firstOrFail();

    $this->bundles->rejectSignature($f1sig, $this->financeA, 'Audit rejection test');

    expect(FinanceAuditLog::where('event_type', 'payment_bundle.signature_rejected')
        ->where('auditable_id', $bundle->id)->exists())->toBeTrue();

    expect(FinanceAuditLog::where('event_type', 'payment_bundle.signature_revoked')
        ->where('auditable_id', $bundle->id)->exists())->toBeTrue();

    expect(FinanceAuditLog::where('event_type', 'payment_bundle.rejected')
        ->where('auditable_id', $bundle->id)->exists())->toBeTrue();

    expect(FinanceAuditLog::where('event_type', 'payment_bundle.item_released')
        ->where('auditable_id', $bundle->id)->exists())->toBeTrue();
});

// ─── 3. Before/after status on transitions ────────────────────────────────────

it('submitted_for_signature has before=draft and after=pending_finance_signature_1', function () {
    $pr = auditApprovedPr14();
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, $pr, $this->creator);
    $this->bundles->submitForSignature($bundle->fresh(), auditValidAssignment14(), $this->creator);

    $row = FinanceAuditLog::where('event_type', 'payment_bundle.submitted_for_signature')
        ->where('auditable_id', $bundle->id)
        ->firstOrFail();

    expect($row->before_snapshot['status'])->toBe(PaymentBundle::STATUS_DRAFT);
    expect($row->after_snapshot['status'])->toBe(PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_1);
});

it('authorized has before=pending_executive_signature and after=authorized_for_payment', function () {
    $pr = auditApprovedPr14();
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, $pr, $this->creator);
    $bundle = $this->bundles->submitForSignature($bundle->fresh(), auditValidAssignment14(), $this->creator);

    auditSignSlot14($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_1);
    auditSignSlot14($bundle->fresh(), PaymentBundleSignatureSlot::FINANCE_SIGNER_2);
    auditSignSlot14($bundle->fresh(), PaymentBundleSignatureSlot::EXECUTIVE_SIGNER);

    $row = FinanceAuditLog::where('event_type', 'payment_bundle.authorized')
        ->where('auditable_id', $bundle->id)
        ->firstOrFail();

    expect($row->before_snapshot['status'])->toBe(PaymentBundle::STATUS_PENDING_EXECUTIVE_SIGNATURE);
    expect($row->after_snapshot['status'])->toBe(PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT);
    // existing after keys preserved
    expect($row->after_snapshot)->toHaveKey('canonical_payload_hash');
    expect($row->after_snapshot)->toHaveKey('authorized_at');
});

it('marked_paid has before status and after.status=paid, and existing after keys intact', function () {
    $bundle = auditFullLifecycleBundle14();

    $this->payments->markPaid($bundle, $this->payer, auditFakePdf14('proof-status.pdf'));

    $row = FinanceAuditLog::where('event_type', 'payment_bundle.marked_paid')
        ->where('auditable_id', $bundle->id)
        ->firstOrFail();

    expect($row->before_snapshot['status'])->not->toBeNull();
    expect($row->after_snapshot['status'])->toBe(PaymentBundle::STATUS_PAID);
    // existing after keys preserved
    expect($row->after_snapshot)->toHaveKey('paid_at');
    expect($row->after_snapshot)->toHaveKey('total_amount');
});

it('voided has before status and after.status=voided', function () {
    $pr = auditApprovedPr14();
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, $pr, $this->creator);
    $this->bundles->cancel($bundle->fresh(), $this->creator);

    // Use a fresh draft for the void test.
    $pr2 = auditApprovedPr14();
    $bundle2 = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle2, $pr2, $this->creator);

    $this->bundles->void($bundle2->fresh(), $this->creator, 'Status snapshot test');

    $row = FinanceAuditLog::where('event_type', 'payment_bundle.voided')
        ->where('auditable_id', $bundle2->id)
        ->firstOrFail();

    expect($row->before_snapshot['status'])->toBe(PaymentBundle::STATUS_DRAFT);
    expect($row->after_snapshot['status'])->toBe(PaymentBundle::STATUS_VOIDED);
    expect($row->after_snapshot['reason'])->toBe('Status snapshot test');
});

it('cancelled has before=draft and after.status=cancelled', function () {
    $pr = auditApprovedPr14();
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, $pr, $this->creator);

    $this->bundles->cancel($bundle->fresh(), $this->creator);

    $row = FinanceAuditLog::where('event_type', 'payment_bundle.cancelled')
        ->where('auditable_id', $bundle->id)
        ->firstOrFail();

    expect($row->before_snapshot['status'])->toBe(PaymentBundle::STATUS_DRAFT);
    expect($row->after_snapshot['status'])->toBe(PaymentBundle::STATUS_CANCELLED);
});

it('payment_recording_started has before=authorized_for_payment and after.status=payment_recording', function () {
    $bundle = auditFullLifecycleBundle14();

    $row = FinanceAuditLog::where('event_type', 'payment_bundle.payment_recording_started')
        ->where('auditable_id', $bundle->id)
        ->firstOrFail();

    expect($row->before_snapshot['status'])->toBe(PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT);
    expect($row->after_snapshot['status'])->toBe(PaymentBundle::STATUS_PAYMENT_RECORDING);
});

it('payment_proof_rejected has before=paid and after.status=payment_proof_rejected', function () {
    $bundle = auditFullLifecycleBundle14();
    $this->payments->markPaid($bundle, $this->payer, auditFakePdf14('proof-rej.pdf'));
    $bundle->refresh();

    $this->payments->rejectPaymentProof($bundle, $this->creator, 'Blurry scan');

    $row = FinanceAuditLog::where('event_type', 'payment_bundle.payment_proof_rejected')
        ->where('auditable_id', $bundle->id)
        ->firstOrFail();

    expect($row->before_snapshot['status'])->toBe(PaymentBundle::STATUS_PAID);
    expect($row->after_snapshot['status'])->toBe(PaymentBundle::STATUS_PAYMENT_PROOF_REJECTED);
    expect($row->reason)->toBe('Blurry scan');
});

it('rejected has before status and after.status=rejected', function () {
    $pr = auditApprovedPr14();
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, $pr, $this->creator);
    $bundle = $this->bundles->submitForSignature($bundle->fresh(), auditValidAssignment14(), $this->creator);

    $f1sig = $bundle->signatures()
        ->where('signature_slot', PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value)
        ->firstOrFail();

    $this->bundles->rejectSignature($f1sig, $this->financeA, 'Status snapshot for rejected');

    $row = FinanceAuditLog::where('event_type', 'payment_bundle.rejected')
        ->where('auditable_id', $bundle->id)
        ->firstOrFail();

    expect($row->before_snapshot['status'])->not->toBeNull();
    expect($row->after_snapshot['status'])->toBe(PaymentBundle::STATUS_REJECTED);
});

// ─── 4. Hash chain valid ──────────────────────────────────────────────────────

it('hash chain is valid and unbroken after a full lifecycle', function () {
    $bundle = auditFullLifecycleBundle14();
    $this->payments->markPaid($bundle, $this->payer, auditFakePdf14('proof-chain.pdf'));

    $logs = FinanceAuditLog::orderBy('id')->get();

    expect($logs)->not->toBeEmpty();

    $prevHash = null;
    foreach ($logs as $log) {
        // Each row's current_hash must match recompute().
        expect($this->logger->recompute($log))->toBe(
            $log->current_hash,
            "Hash mismatch on log id={$log->id} event={$log->event_type}"
        );

        // Each row's previous_hash must match the prior row's current_hash.
        expect($log->previous_hash)->toBe(
            $prevHash,
            "Chain break at log id={$log->id} event={$log->event_type}"
        );

        $prevHash = $log->current_hash;
    }
});
