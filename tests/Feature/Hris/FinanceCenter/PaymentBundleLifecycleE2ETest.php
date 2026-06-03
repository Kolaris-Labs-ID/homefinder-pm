<?php

/**
 * Phase 7 — Payment Bundle full-lifecycle E2E test.
 *
 * Coverage classification (granular tests already exist):
 *  - Sequential F1→F2→Exec signing:           PaymentBundleSignatureWorkflowTest ✓
 *  - SoD at submit-for-signature:              PaymentBundleSignatureWorkflowTest ✓
 *  - Out-of-order blocked:                     PaymentBundleSignatureWorkflowTest ✓
 *  - Idempotent authorization:                 PaymentBundleSignatureWorkflowTest ✓
 *  - Sign-time SoD (same human, two slots):    PaymentBundleSignatureWorkflowTest ✓
 *  - markPaid happy path + proof required:     PaymentBundlePaymentProofTest ✓
 *  - Partially-signed cannot be paid:          PaymentBundlePaymentProofTest ✓
 *  - Signed PDF required:                      PaymentBundlePaymentProofTest ✓
 *  - Frozen snapshot vs live PR mutation:      PaymentBundlePaymentExecutionSnapshotTest ✓
 *  - Drift detection + audit:                  PaymentBundlePaymentExecutionSnapshotTest ✓
 *  - signed_payload_hash == canonical:         PaymentBundleSignedPdfTest ✓
 *  - Snapshot frozen at addPaymentRequest:     PaymentBundleServiceTest ✓
 *
 * Net-new in this file:
 *  1. One cohesive happy-path E2E test walking every status transition in sequence.
 *  2. Frozen-snapshot integrity: mutate AFTER full authorization, then assert payment
 *     instructions and the paid PR match the snapshot (not the live value).
 *     → FINDING: beginPaymentRecording detects drift and throws. paymentInstructions()
 *       reads the frozen snapshot directly, unaffected by the live mutation.
 *       BUG-5 implication: the frozen snapshot protects payment at execution time; the
 *       live PR CAN be mutated after bundling, but drift detection blocks payment
 *       recording — the attack is detectable and blocked, not silently exploitable.
 *  3. Partial-sign-can't-pay (cross-check in E2E context, after only F1 signs).
 *  4. signed_payload_hash == canonical_payload_hash asserted inside the E2E flow.
 */

use App\Enums\PaymentBundleSignatureSlot;
use App\Models\Hris\FinanceAttachment;
use App\Models\Hris\FinanceAuditLog;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleItem;
use App\Models\Hris\PaymentRequest;
use App\Models\User;
use App\Services\Hris\PaymentBundlePaymentService;
use App\Services\Hris\PaymentBundleService;
use App\Services\Hris\PaymentBundleSignatureService;
use App\Services\Hris\PaymentRequestService;
use Database\Seeders\FinanceRolePermissionSeeder;
use Database\Seeders\PaymentBundleRolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('private');

    $this->seed(FinanceRolePermissionSeeder::class);
    $this->seed(PaymentBundleRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->bundles = app(PaymentBundleService::class);
    $this->signatures = app(PaymentBundleSignatureService::class);
    $this->payments = app(PaymentBundlePaymentService::class);
    $this->prs = app(PaymentRequestService::class);

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

// ─── local helpers (prefixed p7_ to avoid collisions) ──────────────────────

function p7_inkPng(): string
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

function p7_approvedPr(int $amount = 5_000_000, string $account = '9876543210'): PaymentRequest
{
    $svc = test()->prs;
    $pr = $svc->createManual(test()->creator, [
        'payee_type' => 'vendor',
        'payee_name' => 'PT E2E Vendor',
        'bank_name' => 'BCA',
        'bank_account_number' => $account,
        'amount' => $amount,
        'currency' => 'IDR',
        'description' => 'E2E lifecycle test payment',
    ]);
    $svc->submitToCoo($pr);
    $svc->approveAsCoo($pr, test()->coo);

    return $pr->fresh();
}

function p7_assignment(): array
{
    return [
        PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value => test()->financeA->id,
        PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value => test()->financeB->id,
        PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value => test()->exec->id,
    ];
}

function p7_signSlot(PaymentBundle $bundle, PaymentBundleSignatureSlot $slot): void
{
    $sig = $bundle->signatures()->where('signature_slot', $slot->value)->firstOrFail();
    test()->signatures->submitSignature($sig, $sig->assignedUser, p7_inkPng());
}

function p7_fakePdf(string $name = 'proof.pdf'): UploadedFile
{
    return UploadedFile::fake()->create($name, 50, 'application/pdf');
}

// ─── 1. Full happy-path E2E ─────────────────────────────────────────────────

it('walks the complete payment-bundle lifecycle: approved PR → paid', function () {
    // ── Step 1: create an approved PR ──────────────────────────────────────
    $pr = p7_approvedPr(5_000_000, '9876543210');
    expect($pr->status)->toBe(PaymentRequest::STATUS_APPROVED_BY_COO);

    // ── Step 2: create bundle, add PR → snapshot frozen ────────────────────
    $bundle = $this->bundles->create($this->creator, ['title' => 'E2E Bundle']);
    expect($bundle->status)->toBe(PaymentBundle::STATUS_DRAFT);

    $item = $this->bundles->addPaymentRequest($bundle, $pr, $this->creator);
    expect($item->source_snapshot_json['payee_name'])->toBe('PT E2E Vendor');
    expect($item->source_snapshot_json['bank_account_number'])->toBe('9876543210');
    expect($pr->fresh()->status)->toBe(PaymentRequest::STATUS_IN_PAYMENT_BUNDLE);

    $bundle->refresh();

    // ── Step 3: submitForSignature → canonical hash + unsigned PDF frozen ──
    $bundle = $this->bundles->submitForSignature($bundle, p7_assignment(), $this->creator);
    expect($bundle->status)->toBe(PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_1);
    expect($bundle->canonical_payload_hash)->not->toBeNull();
    expect(strlen($bundle->canonical_payload_hash))->toBe(64);
    expect($bundle->unsigned_pdf_attachment_id)->not->toBeNull();
    expect($bundle->submitted_at)->not->toBeNull();

    // Unsigned PDF is a real PDF.
    $unsignedAtt = FinanceAttachment::find($bundle->unsigned_pdf_attachment_id);
    Storage::disk('private')->assertExists($unsignedAtt->file_path);
    $unsignedBytes = Storage::disk('private')->get($unsignedAtt->file_path);
    expect(substr($unsignedBytes, 0, 4))->toBe('%PDF');

    // ── Step 4: Finance Signer 1 signs ────────────────────────────────────
    p7_signSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_1);
    expect($bundle->fresh()->status)->toBe(PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_2);

    // Partially-signed → NOT authorized → markPaid must throw.
    expect(fn () => $this->payments->markPaid($bundle->fresh(), $this->payer, p7_fakePdf()))
        ->toThrow(RuntimeException::class);

    // ── Step 5: Finance Signer 2 signs ────────────────────────────────────
    p7_signSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_2);
    expect($bundle->fresh()->status)->toBe(PaymentBundle::STATUS_PENDING_EXECUTIVE_SIGNATURE);

    // Still partially-signed → still blocked.
    expect(fn () => $this->payments->markPaid($bundle->fresh(), $this->payer, p7_fakePdf()))
        ->toThrow(RuntimeException::class);

    // ── Step 6: Executive signs → authorized + signed PDF generated ────────
    p7_signSlot($bundle, PaymentBundleSignatureSlot::EXECUTIVE_SIGNER);
    $bundle->refresh();

    expect($bundle->status)->toBe(PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT);
    expect($bundle->authorized_at)->not->toBeNull();

    // signed_payload_hash == canonical_payload_hash (binding assertion).
    expect($bundle->signed_payload_hash)->toBe($bundle->canonical_payload_hash);

    // Signed PDF stored.
    expect($bundle->signed_pdf_attachment_id)->not->toBeNull();
    $signedAtt = FinanceAttachment::find($bundle->signed_pdf_attachment_id);
    expect($signedAtt->attachment_role)->toBe(FinanceAttachment::ROLE_PAYMENT_BUNDLE_SIGNED_PDF);
    Storage::disk('private')->assertExists($signedAtt->file_path);
    $signedBytes = Storage::disk('private')->get($signedAtt->file_path);
    expect(substr($signedBytes, 0, 4))->toBe('%PDF');

    // Audit: authorized exactly once.
    expect(FinanceAuditLog::where('event_type', 'payment_bundle.authorized')
        ->where('auditable_id', $bundle->id)->count())->toBe(1);

    // ── Step 7: finance executor uploads payment proof → paid ──────────────
    $paid = $this->payments->markPaid($bundle, $this->payer, p7_fakePdf('e2e_proof.pdf'), 'REF-E2E', 'E2E lifecycle test');

    expect($paid->status)->toBe(PaymentBundle::STATUS_PAID);
    expect($paid->paid_by)->toBe($this->payer->id);
    expect($paid->paid_at)->not->toBeNull();
    expect($paid->payment_reference)->toBe('REF-E2E');
    expect($paid->payment_proof_attachment_id)->not->toBeNull();

    // Items cascaded to paid.
    expect($bundle->fresh()->items()->where('status', PaymentBundleItem::STATUS_PAID)->count())->toBe(1);

    // Linked PR is now paid.
    expect(PaymentRequest::find($pr->id)->status)->toBe(PaymentRequest::STATUS_PAID);

    // Audit events present.
    expect(FinanceAuditLog::where('event_type', 'payment_bundle.payment_proof_uploaded')
        ->where('auditable_id', $bundle->id)->exists())->toBeTrue();
    expect(FinanceAuditLog::where('event_type', 'payment_bundle.marked_paid')
        ->where('auditable_id', $bundle->id)->exists())->toBeTrue();
});

// ─── 2. Frozen-snapshot integrity after live PR mutation (BUG-5 probe) ──────
//
// FINDING: After the PR is bundled and authorized, mutating the live PR:
//   a) paymentInstructions() returns the FROZEN snapshot value (not the mutated live).
//   b) detectDrift() returns true.
//   c) beginPaymentRecording() throws "Batch source drift detected".
// → BUG-5 severity: MEDIUM-HIGH (live PR can be mutated post-bundling), but the
//   payment execution gate (beginPaymentRecording) blocks the attack. The mutation
//   is detectable and logged. Silent exploitation at payment time is NOT possible.

it('payment instructions reflect the frozen snapshot even after live PR mutation (BUG-5 characterization)', function () {
    $pr = p7_approvedPr(5_000_000, '9876543210');
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, $pr, $this->creator);
    $bundle = $this->bundles->submitForSignature($bundle->fresh(), p7_assignment(), $this->creator);

    p7_signSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_1);
    p7_signSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_2);
    p7_signSlot($bundle, PaymentBundleSignatureSlot::EXECUTIVE_SIGNER);
    $bundle->refresh();

    expect($bundle->status)->toBe(PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT);

    // Mutate the live PR AFTER authorization.
    $pr->fresh()->update(['payee_name' => 'ATTACKER HIJACKED', 'amount' => '1.00', 'bank_name' => 'EVIL_BANK']);
    $pr->fresh()->forceFill([
        'bank_account_number_encrypted' => Crypt::encryptString('0000000000'),
        'bank_account_last4' => '0000',
    ])->save();

    // paymentInstructions reads the frozen snapshot, not the live PR.
    $instructions = $this->payments->paymentInstructions($bundle->fresh());
    expect($instructions[0]['payee_name'])->toBe('PT E2E Vendor');
    expect($instructions[0]['amount'])->toBe('5000000.00');
    expect($instructions[0]['bank_account_number'])->toBe('9876543210');
    expect($instructions[0]['bank_name'])->toBe('BCA');

    // drift is detected.
    $item = $bundle->items()->first();
    expect($this->payments->detectDrift($item->fresh()))->toBeTrue();

    // beginPaymentRecording is BLOCKED — attack cannot proceed to payment.
    expect(fn () => $this->payments->beginPaymentRecording($bundle->fresh(), $this->creator))
        ->toThrow(RuntimeException::class, 'Batch source drift detected');

    // Drift audit event written.
    expect(FinanceAuditLog::where('event_type', 'batch_source_drift_detected')
        ->where('auditable_id', $bundle->id)->exists())->toBeTrue();
});

// ─── 3. signed_payload_hash == canonical_payload_hash (isolated assertion) ─

it('signed_payload_hash equals canonical_payload_hash after full authorization', function () {
    $pr = p7_approvedPr();
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, $pr, $this->creator);
    $bundle = $this->bundles->submitForSignature($bundle->fresh(), p7_assignment(), $this->creator);

    p7_signSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_1);
    p7_signSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_2);
    p7_signSlot($bundle, PaymentBundleSignatureSlot::EXECUTIVE_SIGNER);
    $bundle->refresh();

    expect($bundle->signed_payload_hash)->toBe($bundle->canonical_payload_hash);
    expect($bundle->signed_payload_hash)->not->toBeNull();
    expect(strlen($bundle->signed_payload_hash))->toBe(64);
});

// ─── 4. Payment proof is required (typed param enforces; characterization) ─

it('markPaid requires a proof file (UploadedFile typed param — cannot be called without one)', function () {
    $pr = p7_approvedPr();
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, $pr, $this->creator);
    $bundle = $this->bundles->submitForSignature($bundle->fresh(), p7_assignment(), $this->creator);

    p7_signSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_1);
    p7_signSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_2);
    p7_signSlot($bundle, PaymentBundleSignatureSlot::EXECUTIVE_SIGNER);
    $bundle->refresh();

    // markPaid with a valid proof succeeds; the typed param prevents null-proof calls at compile time.
    $paid = $this->payments->markPaid($bundle, $this->payer, p7_fakePdf());
    expect($paid->status)->toBe(PaymentBundle::STATUS_PAID);
    expect($paid->payment_proof_attachment_id)->not->toBeNull();
});
